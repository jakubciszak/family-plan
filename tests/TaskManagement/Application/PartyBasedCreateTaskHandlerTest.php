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
use App\TaskManagement\Application\Handler\PartyBasedCreateTaskHandler;
use App\TaskManagement\Domain\Exception\TaskCreationNotAllowedException;
use App\TaskManagement\Infrastructure\Persistence\InMemoryTaskRepository;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\TaskManagement\Mother\FrequencyMother;
use App\Tests\TaskManagement\Mother\PointsMother;
use App\Tests\TaskManagement\Mother\TaskNameMother;
use App\UserManagement\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

/**
 * TDD: Party-based Task Creation Handler
 *
 * Tests task creation using the new Party archetype model:
 * - Person (User) → ADMIN_OF → Organization (Team)
 * - Person (User) → MEMBER_OF → Organization (Team)
 *
 * Requirements:
 * 1. Tasks can only be created within an organization (team)
 * 2. Only persons with ADMIN_OF relationship can create tasks
 * 3. Persons with MEMBER_OF relationship cannot create tasks
 */
class PartyBasedCreateTaskHandlerTest extends TestCase
{
    private InMemoryTaskRepository $taskRepository;
    private InMemoryPartyRepository $partyRepository;
    private InMemoryPartyRelationshipRepository $relationshipRepository;
    private PartyAdapter $partyAdapter;
    private PartyBasedCreateTaskHandler $handler;

    protected function setUp(): void
    {
        $this->taskRepository = new InMemoryTaskRepository();
        $this->partyRepository = new InMemoryPartyRepository();
        $this->relationshipRepository = new InMemoryPartyRelationshipRepository();
        $this->partyAdapter = new PartyAdapter(
            $this->partyRepository,
            $this->relationshipRepository
        );
        $this->handler = new PartyBasedCreateTaskHandler(
            $this->taskRepository,
            $this->relationshipRepository
        );
    }

    public function testTaskCannotBeCreatedWithoutOrganization(): void
    {
        // Given - A person tries to create a task without specifying an organization
        $taskId = UuidMother::random();
        $personId = UuidMother::random();

        $command = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $personId->value(),
            null // No organization (team) ID
        );

        // Then - Expect exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Task must belong to a team');

        // When - Try to create the task
        ($this->handler)($command);
    }

    public function testTaskCanBeCreatedByOrganizationAdmin(): void
    {
        // Given - An organization with an admin person
        $organizationId = UuidMother::random();
        $adminPersonId = UuidMother::random();

        $organization = Organization::create(
            $organizationId,
            'Smith Family',
            'The Smith family household'
        );
        $this->partyRepository->save($organization);

        $adminPerson = Person::create(
            $adminPersonId,
            'Alice Smith',
            Email::fromString('alice@example.com')
        );
        $this->partyRepository->save($adminPerson);

        // Create ADMIN_OF relationship
        $relationship = PartyRelationship::create(
            UuidMother::random(),
            $adminPerson,
            $organization,
            PartyRelationshipType::adminOf()
        );
        $this->relationshipRepository->save($relationship);

        $taskId = UuidMother::random();
        $command = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $adminPersonId->value(),
            $organizationId->value()
        );

        // When - Admin creates a task
        ($this->handler)($command);

        // Then - Task is created successfully
        $task = $this->taskRepository->findById($taskId);
        $this->assertNotNull($task);
        $this->assertEquals($organizationId->value(), $task->teamId()?->value());
    }

    public function testTaskCannotBeCreatedByRegularMember(): void
    {
        // Given - An organization with a regular member (MEMBER_OF, not ADMIN_OF)
        $organizationId = UuidMother::random();
        $memberId = UuidMother::random();

        $organization = Organization::create(
            $organizationId,
            'Jones Family',
            null
        );
        $this->partyRepository->save($organization);

        $memberPerson = Person::create(
            $memberId,
            'Bob Jones',
            Email::fromString('bob@example.com')
        );
        $this->partyRepository->save($memberPerson);

        // Create MEMBER_OF relationship (not admin)
        $relationship = PartyRelationship::create(
            UuidMother::random(),
            $memberPerson,
            $organization,
            PartyRelationshipType::memberOf()
        );
        $this->relationshipRepository->save($relationship);

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

    public function testTaskCannotBeCreatedByPersonNotInOrganization(): void
    {
        // Given - An organization and a person with no relationship
        $organizationId = UuidMother::random();
        $outsiderId = UuidMother::random();

        $organization = Organization::create(
            $organizationId,
            'Brown Family',
            null
        );
        $this->partyRepository->save($organization);

        $outsiderPerson = Person::create(
            $outsiderId,
            'Charlie Brown',
            Email::fromString('charlie@example.com')
        );
        $this->partyRepository->save($outsiderPerson);

        // No relationship created

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

    public function testMultipleAdminsCanCreateTasksInSameOrganization(): void
    {
        // Given - An organization with two admins
        $organizationId = UuidMother::random();
        $admin1Id = UuidMother::random();
        $admin2Id = UuidMother::random();

        $organization = Organization::create(
            $organizationId,
            'Davis Family',
            null
        );
        $this->partyRepository->save($organization);

        $admin1 = Person::create(
            $admin1Id,
            'Dave Davis',
            Email::fromString('dave@example.com')
        );
        $this->partyRepository->save($admin1);

        $admin2 = Person::create(
            $admin2Id,
            'Diana Davis',
            Email::fromString('diana@example.com')
        );
        $this->partyRepository->save($admin2);

        // Create ADMIN_OF relationships for both
        $relationship1 = PartyRelationship::create(
            UuidMother::random(),
            $admin1,
            $organization,
            PartyRelationshipType::adminOf()
        );
        $this->relationshipRepository->save($relationship1);

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

    public function testEndedAdminRelationshipCannotCreateTasks(): void
    {
        // Given - An organization with an admin whose relationship has ended
        $organizationId = UuidMother::random();
        $formerAdminId = UuidMother::random();

        $organization = Organization::create(
            $organizationId,
            'Evans Family',
            null
        );
        $this->partyRepository->save($organization);

        $formerAdmin = Person::create(
            $formerAdminId,
            'Eve Evans',
            Email::fromString('eve@example.com')
        );
        $this->partyRepository->save($formerAdmin);

        // Create ADMIN_OF relationship but end it
        $relationship = PartyRelationship::create(
            UuidMother::random(),
            $formerAdmin,
            $organization,
            PartyRelationshipType::adminOf()
        );
        $relationship->end(new \DateTimeImmutable());
        $this->relationshipRepository->save($relationship);

        $taskId = UuidMother::random();
        $command = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $formerAdminId->value(),
            $organizationId->value()
        );

        // Then - Expect exception
        $this->expectException(TaskCreationNotAllowedException::class);
        $this->expectExceptionMessage('Only team administrators can create tasks');

        // When - Former admin tries to create a task
        ($this->handler)($command);
    }
}
