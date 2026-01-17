<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Application;

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
use PHPUnit\Framework\TestCase;

/**
 * Test-driven development: Team admin-only task creation
 *
 * Requirements:
 * 1. Tasks can only be created within a team
 * 2. Tasks cannot exist without a team
 * 3. Only team admins can create tasks
 * 4. Regular team members cannot create tasks
 */
class CreateTaskWithTeamPermissionsTest extends TestCase
{
    private InMemoryTaskRepository $taskRepository;
    private InMemoryTeamRepository $teamRepository;
    private InMemoryTeamMemberRepository $teamMemberRepository;
    private CreateTaskHandler $handler;

    protected function setUp(): void
    {
        $this->taskRepository = new InMemoryTaskRepository();
        $this->teamRepository = new InMemoryTeamRepository();
        $this->teamMemberRepository = new InMemoryTeamMemberRepository();
        $this->handler = new CreateTaskHandler(
            $this->taskRepository,
            $this->teamMemberRepository
        );
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
        // Given - A team with an admin user
        $teamId = UuidMother::random();
        $adminId = UuidMother::random();

        $team = Team::create($teamId, TeamName::fromString('Family'), 'My family', $adminId);
        $this->teamRepository->save($team);

        $teamMember = TeamMember::create(
            UuidMother::random(),
            $teamId,
            $adminId,
            TeamRole::admin()
        );
        $this->teamMemberRepository->save($teamMember);

        $taskId = UuidMother::random();
        $command = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value()
        );

        // When - Admin creates a task
        ($this->handler)($command);

        // Then - Task is created successfully
        $task = $this->taskRepository->findById($taskId);
        $this->assertNotNull($task);
        $this->assertEquals($teamId->value(), $task->teamId()?->value());
    }

    public function testTaskCannotBeCreatedByRegularTeamMember(): void
    {
        // Given - A team with a regular member (not admin)
        $teamId = UuidMother::random();
        $adminId = UuidMother::random();
        $memberId = UuidMother::random();

        $team = Team::create($teamId, TeamName::fromString('Family'), 'My family', $adminId);
        $this->teamRepository->save($team);

        // Create admin
        $adminMember = TeamMember::create(
            UuidMother::random(),
            $teamId,
            $adminId,
            TeamRole::admin()
        );
        $this->teamMemberRepository->save($adminMember);

        // Create regular member
        $regularMember = TeamMember::create(
            UuidMother::random(),
            $teamId,
            $memberId,
            TeamRole::member()
        );
        $this->teamMemberRepository->save($regularMember);

        $taskId = UuidMother::random();
        $command = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $memberId->value(),
            $teamId->value()
        );

        // Then - Expect exception
        $this->expectException(TaskCreationNotAllowedException::class);
        $this->expectExceptionMessage('Only team administrators can create tasks');

        // When - Regular member tries to create a task
        ($this->handler)($command);
    }

    public function testTaskCannotBeCreatedByUserNotInTeam(): void
    {
        // Given - A team and a user who is not a member
        $teamId = UuidMother::random();
        $adminId = UuidMother::random();
        $outsiderId = UuidMother::random();

        $team = Team::create($teamId, TeamName::fromString('Family'), 'My family', $adminId);
        $this->teamRepository->save($team);

        $adminMember = TeamMember::create(
            UuidMother::random(),
            $teamId,
            $adminId,
            TeamRole::admin()
        );
        $this->teamMemberRepository->save($adminMember);

        $taskId = UuidMother::random();
        $command = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $outsiderId->value(),
            $teamId->value()
        );

        // Then - Expect exception
        $this->expectException(TaskCreationNotAllowedException::class);
        $this->expectExceptionMessage('Only team administrators can create tasks');

        // When - Outsider tries to create a task
        ($this->handler)($command);
    }

    public function testTaskCannotBeCreatedForNonExistentTeam(): void
    {
        // Given - A non-existent team ID
        $nonExistentTeamId = UuidMother::random();
        $userId = UuidMother::random();

        $taskId = UuidMother::random();
        $command = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $userId->value(),
            $nonExistentTeamId->value()
        );

        // Then - Expect exception
        $this->expectException(TaskCreationNotAllowedException::class);
        $this->expectExceptionMessage('Only team administrators can create tasks');

        // When - Try to create task for non-existent team
        ($this->handler)($command);
    }

    public function testMultipleAdminsCanCreateTasksInSameTeam(): void
    {
        // Given - A team with two admins
        $teamId = UuidMother::random();
        $admin1Id = UuidMother::random();
        $admin2Id = UuidMother::random();

        $team = Team::create($teamId, TeamName::fromString('Family'), 'My family', $admin1Id);
        $this->teamRepository->save($team);

        $admin1Member = TeamMember::create(
            UuidMother::random(),
            $teamId,
            $admin1Id,
            TeamRole::admin()
        );
        $this->teamMemberRepository->save($admin1Member);

        $admin2Member = TeamMember::create(
            UuidMother::random(),
            $teamId,
            $admin2Id,
            TeamRole::admin()
        );
        $this->teamMemberRepository->save($admin2Member);

        // When - Both admins create tasks
        $task1Id = UuidMother::random();
        $command1 = new CreateTaskCommand(
            $task1Id->value(),
            TaskNameMother::create('Task 1')->value(),
            'Description 1',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $admin1Id->value(),
            $teamId->value()
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
            $teamId->value()
        );
        ($this->handler)($command2);

        // Then - Both tasks are created successfully
        $task1 = $this->taskRepository->findById($task1Id);
        $task2 = $this->taskRepository->findById($task2Id);

        $this->assertNotNull($task1);
        $this->assertNotNull($task2);
        $this->assertEquals($teamId->value(), $task1->teamId()?->value());
        $this->assertEquals($teamId->value(), $task2->teamId()?->value());
    }
}
