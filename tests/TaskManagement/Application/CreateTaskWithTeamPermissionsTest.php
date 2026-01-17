<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Application;

use App\Party\Application\Service\PartyAdapter;
use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRelationshipRepository;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Application\Handler\CreateTaskHandler;
use App\TaskManagement\Domain\Exception\TaskCreationNotAllowedException;
use App\TaskManagement\Infrastructure\Persistence\InMemoryTaskRepository;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\Entity\TeamMember;
use App\TeamManagement\Domain\ValueObject\TeamName;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\TeamManagement\Infrastructure\Persistence\InMemoryTeamMemberRepository;
use App\TeamManagement\Infrastructure\Persistence\InMemoryTeamRepository;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\TaskManagement\Mother\FrequencyMother;
use App\Tests\TaskManagement\Mother\PointsMother;
use App\Tests\TaskManagement\Mother\TaskNameMother;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Test-driven development: Team admin-only task creation
 *
 * MIGRATED TO PARTY ARCHETYPE:
 * Tests now use Party model (Person, Organization, PartyRelationship)
 * instead of legacy User/Team/TeamMember model.
 *
 * Requirements:
 * 1. Tasks can only be created within an organization (team)
 * 2. Tasks cannot exist without an organization
 * 3. Only persons with ADMIN_OF relationship can create tasks
 * 4. Regular members (MEMBER_OF relationship) cannot create tasks
 */
class CreateTaskWithTeamPermissionsTest extends TestCase
{
    private InMemoryTaskRepository $taskRepository;
    private InMemoryPartyRepository $partyRepository;
    private InMemoryPartyRelationshipRepository $relationshipRepository;
    private PartyAdapter $partyAdapter;
    private CreateTaskHandler $handler;

    protected function setUp(): void
    {
        $this->taskRepository = new InMemoryTaskRepository();
        $this->partyRepository = new InMemoryPartyRepository();
        $this->relationshipRepository = new InMemoryPartyRelationshipRepository();
        $this->partyAdapter = new PartyAdapter(
            $this->partyRepository,
            $this->relationshipRepository
        );
        $this->handler = new CreateTaskHandler(
            $this->taskRepository,
            $this->relationshipRepository
        );
    }

    /**
     * Helper: Create Person and Organization with ADMIN_OF relationship
     */
    private function createAdminRelationship(Uuid $personId, Uuid $organizationId, string $personName, string $orgName): void
    {
        $email = strtolower(str_replace(' ', '', $personName)) . '@example.com';
        $person = Person::create($personId, $personName, Email::fromString($email));
        $this->partyRepository->save($person);

        $organization = Organization::create($organizationId, $orgName, null);
        $this->partyRepository->save($organization);

        $relationship = PartyRelationship::create(
            UuidMother::random(),
            $person,
            $organization,
            PartyRelationshipType::adminOf()
        );
        $this->relationshipRepository->save($relationship);
    }

    /**
     * Helper: Create Person and Organization with MEMBER_OF relationship
     */
    private function createMemberRelationship(Uuid $personId, Uuid $organizationId, string $personName, string $orgName): void
    {
        $email = strtolower(str_replace(' ', '', $personName)) . '@example.com';
        $person = Person::create($personId, $personName, Email::fromString($email));
        $this->partyRepository->save($person);

        $organization = Organization::create($organizationId, $orgName, null);
        $this->partyRepository->save($organization);

        $relationship = PartyRelationship::create(
            UuidMother::random(),
            $person,
            $organization,
            PartyRelationshipType::memberOf()
        );
        $this->relationshipRepository->save($relationship);
    }

    public function testTaskCannotBeCreatedWithoutTeam(): void
    {
        // Given - A user tries to create a task without specifying a team
        $taskId = UuidMother::random();
        $userId = UuidMother::random();

        $command = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $userId->value(),
            null // No team ID
        );

        // Then - Expect exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Task must belong to a team');

        // When - Try to create the task
        ($this->handler)($command);
    }

    public function testTaskCanBeCreatedByTeamAdmin(): void
    {
        // Given - An organization with an admin person
        $organizationId = UuidMother::random();
        $adminId = UuidMother::random();

        $this->createAdminRelationship($adminId, $organizationId, 'Admin User', 'Smith Family');

        $taskId = UuidMother::random();
        $command = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $adminId->value(),
            $organizationId->value()
        );

        // When - Admin creates a task
        ($this->handler)($command);

        // Then - Task is created successfully
        $task = $this->taskRepository->findById($taskId);
        $this->assertNotNull($task);
        $this->assertEquals($organizationId->value(), $task->teamId()?->value());
    }

    public function testTaskCannotBeCreatedByRegularTeamMember(): void
    {
        // Given - An organization with a regular member (MEMBER_OF, not ADMIN_OF)
        $organizationId = UuidMother::random();
        $memberId = UuidMother::random();

        $this->createMemberRelationship($memberId, $organizationId, 'Regular Member', 'Jones Family');

        $taskId = UuidMother::random();
        $command = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $memberId->value(),
            $organizationId->value()
        );

        // Then - Expect exception
        $this->expectException(TaskCreationNotAllowedException::class);
        $this->expectExceptionMessage('Only team administrators can create tasks');

        // When - Regular member tries to create a task
        ($this->handler)($command);
    }

    public function testTaskCannotBeCreatedByUserNotInTeam(): void
    {
        // Given - An organization and a person with no relationship
        $organizationId = UuidMother::random();
        $outsiderId = UuidMother::random();

        // Create organization (without any relationship to the outsider)
        $organization = Organization::create($organizationId, 'Brown Family', null);
        $this->partyRepository->save($organization);

        $taskId = UuidMother::random();
        $command = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $outsiderId->value(),
            $organizationId->value()
        );

        // Then - Expect exception
        $this->expectException(TaskCreationNotAllowedException::class);
        $this->expectExceptionMessage('Only team administrators can create tasks');

        // When - Outsider tries to create a task
        ($this->handler)($command);
    }

    public function testTaskCannotBeCreatedForNonExistentTeam(): void
    {
        // Given - A non-existent organization ID
        $nonExistentOrgId = UuidMother::random();
        $personId = UuidMother::random();

        $taskId = UuidMother::random();
        $command = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $personId->value(),
            $nonExistentOrgId->value()
        );

        // Then - Expect exception
        $this->expectException(TaskCreationNotAllowedException::class);
        $this->expectExceptionMessage('Only team administrators can create tasks');

        // When - Try to create task for non-existent organization
        ($this->handler)($command);
    }

    public function testMultipleAdminsCanCreateTasksInSameTeam(): void
    {
        // Given - An organization with two admins
        $organizationId = UuidMother::random();
        $admin1Id = UuidMother::random();
        $admin2Id = UuidMother::random();

        $this->createAdminRelationship($admin1Id, $organizationId, 'Admin One', 'Davis Family');

        // Create second admin relationship for same organization
        $admin2 = Person::create($admin2Id, 'Admin Two', Email::fromString('admin2@example.com'));
        $this->partyRepository->save($admin2);

        $organization = $this->partyRepository->findById($organizationId);
        $relationship2 = PartyRelationship::create(
            UuidMother::random(),
            $admin2,
            $organization,
            PartyRelationshipType::adminOf()
        );
        $this->relationshipRepository->save($relationship2);

        // When - Both admins create tasks
        $task1Id = UuidMother::random();
        $command1 = new CreateTaskCommand(
            $task1Id->value(),
            TaskNameMother::create('Task 1')->value(),
            'Description 1',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $admin1Id->value(),
            $organizationId->value()
        );
        ($this->handler)($command1);

        $task2Id = UuidMother::random();
        $command2 = new CreateTaskCommand(
            $task2Id->value(),
            TaskNameMother::create('Task 2')->value(),
            'Description 2',
            PointsMother::high()->value(),
            FrequencyMother::weekly()->value,
            $admin2Id->value(),
            $organizationId->value()
        );
        ($this->handler)($command2);

        // Then - Both tasks are created successfully
        $task1 = $this->taskRepository->findById($task1Id);
        $task2 = $this->taskRepository->findById($task2Id);

        $this->assertNotNull($task1);
        $this->assertNotNull($task2);
        $this->assertEquals($organizationId->value(), $task1->teamId()?->value());
        $this->assertEquals($organizationId->value(), $task2->teamId()?->value());
    }
}
