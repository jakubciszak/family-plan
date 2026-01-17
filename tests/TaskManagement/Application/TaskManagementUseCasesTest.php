<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Application;

use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRelationshipRepository;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRepository;
use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Application\Command\CompleteTaskCommand;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Application\Handler\ApproveTaskHandler;
use App\TaskManagement\Application\Handler\CompleteTaskHandler;
use App\TaskManagement\Application\Handler\CreateTaskHandler;
use App\TaskManagement\Infrastructure\Persistence\InMemoryTaskRepository;
use App\TaskManagement\Domain\Policy\AdminApprovalPolicy;
use App\TaskManagement\Domain\Strategy\TaskApprovalPointsAwardStrategy;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use App\PointsManagement\Infrastructure\Persistence\InMemoryUserWalletRepository;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\Entity\TeamMember;
use App\TeamManagement\Domain\ValueObject\TeamName;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\TeamManagement\Infrastructure\Persistence\InMemoryTeamMemberRepository;
use App\TeamManagement\Infrastructure\Persistence\InMemoryTeamRepository;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\TaskManagement\Assert\TaskAssert;
use App\Tests\TaskManagement\Mother\FrequencyMother;
use App\Tests\TaskManagement\Mother\PointsMother;
use App\Tests\TaskManagement\Mother\TaskNameMother;
use App\Tests\UserManagement\Mother\UserMother;
use PHPUnit\Framework\TestCase;

/**
 * Detroit school test for Task Management use cases
 * Tests the complete workflow with real handlers and repository
 *
 * MIGRATED TO PARTY ARCHETYPE
 */
class TaskManagementUseCasesTest extends TestCase
{
    private InMemoryTaskRepository $taskRepository;
    private InMemoryUserRepository $userRepository;
    private InMemoryUserWalletRepository $walletRepository;
    private InMemoryPartyRepository $partyRepository;
    private InMemoryPartyRelationshipRepository $relationshipRepository;
    private FixedClock $clock;
    private CreateTaskHandler $createHandler;
    private CompleteTaskHandler $completeHandler;
    private ApproveTaskHandler $approveHandler;

    protected function setUp(): void
    {
        $this->taskRepository = new InMemoryTaskRepository();
        $this->userRepository = new InMemoryUserRepository();
        $this->walletRepository = new InMemoryUserWalletRepository();
        $this->partyRepository = new InMemoryPartyRepository();
        $this->relationshipRepository = new InMemoryPartyRelationshipRepository();
        $this->clock = new FixedClock();
        $this->createHandler = new CreateTaskHandler($this->taskRepository, $this->relationshipRepository);
        $this->completeHandler = new CompleteTaskHandler($this->taskRepository);

        $approvalPolicy = new AdminApprovalPolicy($this->userRepository);
        $pointsAwardStrategy = new TaskApprovalPointsAwardStrategy($this->walletRepository, $this->clock);
        $this->approveHandler = new ApproveTaskHandler(
            $this->taskRepository,
            $approvalPolicy,
            $pointsAwardStrategy
        );
    }

    private function createTeamWithAdmin(\App\Shared\Domain\ValueObject\Uuid $adminId): \App\Shared\Domain\ValueObject\Uuid
    {
        $organizationId = UuidMother::random();

        // Create Person for admin
        $person = Person::create($adminId, 'Admin User', Email::fromString('admin@example.com'));
        $this->partyRepository->save($person);

        // Create Organization (team)
        $organization = Organization::create($organizationId, 'Test Team', 'Test description');
        $this->partyRepository->save($organization);

        // Create ADMIN_OF relationship
        $relationship = PartyRelationship::create(
            UuidMother::random(),
            $person,
            $organization,
            PartyRelationshipType::adminOf()
        );
        $this->relationshipRepository->save($relationship);

        return $organizationId;
    }

    public function testCreateTaskUseCaseCreatesAndPersistsTask(): void
    {
        // Given
        $adminId = UuidMother::random();
        $teamId = $this->createTeamWithAdmin($adminId);

        $taskId = UuidMother::random();
        $name = TaskNameMother::create('Clean kitchen');
        $description = 'Wash dishes and wipe counters';
        $points = PointsMother::medium();
        $frequency = FrequencyMother::daily();

        $command = new CreateTaskCommand(
            $taskId->value(),
            $name->value(),
            $description,
            $points->value(),
            $frequency->value,
            $adminId->value(),
            $teamId->value(),
            null
        );

        // When
        ($this->createHandler)($command);

        // Then
        $persistedTask = $this->taskRepository->findById($taskId);
        $this->assertNotNull($persistedTask);
        TaskAssert::assertTaskHasId($taskId, $persistedTask);
        TaskAssert::assertTaskHasName($name, $persistedTask);
        TaskAssert::assertTaskHasDescription($description, $persistedTask);
        TaskAssert::assertTaskHasPoints($points, $persistedTask);
        TaskAssert::assertTaskHasFrequency($frequency, $persistedTask);
        TaskAssert::assertTaskIsNew($persistedTask);
    }

    public function testCompleteTaskUseCaseMarksTaskAsCompleted(): void
    {
        // Given - Create a task first
        $adminId = UuidMother::random();
        $teamId = $this->createTeamWithAdmin($adminId);

        $taskId = UuidMother::random();
        $userId = UuidMother::random();

        $createCommand = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create()->value(),
            'Task description',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            null
        );
        ($this->createHandler)($createCommand);

        $completeCommand = new CompleteTaskCommand($taskId->value(), $userId->value());

        // When
        ($this->completeHandler)($completeCommand);

        // Then
        $completedTask = $this->taskRepository->findById($taskId);
        $this->assertNotNull($completedTask);
        TaskAssert::assertTaskIsCompleted($completedTask);
        TaskAssert::assertTaskWasCompletedAt($completedTask);
    }

    public function testApproveTaskUseCaseApprovesCompletedTask(): void
    {
        // Given - Create and complete a task first
        $taskId = UuidMother::random();
        $userId = UuidMother::random();
        $adminId = UuidMother::random();

        $teamId = $this->createTeamWithAdmin($adminId);

        // Create an admin user
        $admin = UserMother::aUser()
            ->withId($adminId)
            ->asAdmin()
            ->build();
        $this->userRepository->save($admin);

        // Create a regular user to assign the task to
        $user = UserMother::aUser()
            ->withId($userId)
            ->asRegularUser()
            ->build();
        $this->userRepository->save($user);

        $createCommand = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create()->value(),
            'Task description',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            $userId->value()
        );
        ($this->createHandler)($createCommand);

        $completeCommand = new CompleteTaskCommand($taskId->value(), $userId->value());
        ($this->completeHandler)($completeCommand);

        $approveCommand = new ApproveTaskCommand($taskId->value(), $adminId->value());

        // When
        ($this->approveHandler)($approveCommand);

        // Then
        $approvedTask = $this->taskRepository->findById($taskId);
        $this->assertNotNull($approvedTask);
        TaskAssert::assertTaskIsApproved($approvedTask);
        TaskAssert::assertTaskWasApprovedAt($approvedTask);

        // Verify points were awarded to wallet
        $wallet = $this->walletRepository->findByUserId($userId);
        $this->assertNotNull($wallet);
        $this->assertEquals(PointsMother::medium()->value(), $wallet->balance()->value());
    }

    public function testCompleteWorkflowFromCreationToApproval(): void
    {
        // Given
        $taskId = UuidMother::random();
        $userId = UuidMother::random();
        $adminId = UuidMother::random();

        $teamId = $this->createTeamWithAdmin($adminId);

        // Create an admin user
        $admin = UserMother::aUser()
            ->withId($adminId)
            ->asAdmin()
            ->build();
        $this->userRepository->save($admin);

        // Create a regular user
        $user = UserMother::aUser()
            ->withId($userId)
            ->asRegularUser()
            ->build();
        $this->userRepository->save($user);

        // When - Create task
        $createCommand = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Important task')->value(),
            'This needs to be done',
            PointsMother::high()->value(),
            FrequencyMother::weekly()->value,
            $adminId->value(),
            $teamId->value(),
            $userId->value()
        );
        ($this->createHandler)($createCommand);

        // Then - Task is created with NEW status
        $task = $this->taskRepository->findById($taskId);
        TaskAssert::assertTaskIsNew($task);
        TaskAssert::assertTaskIsAssignedTo($userId, $task);

        // When - User completes the task
        $completeCommand = new CompleteTaskCommand($taskId->value(), $userId->value());
        ($this->completeHandler)($completeCommand);

        // Then - Task is completed
        $task = $this->taskRepository->findById($taskId);
        TaskAssert::assertTaskIsCompleted($task);

        // When - Admin approves the task
        $approveCommand = new ApproveTaskCommand($taskId->value(), $adminId->value());
        ($this->approveHandler)($approveCommand);

        // Then - Task is approved and points awarded
        $task = $this->taskRepository->findById($taskId);
        TaskAssert::assertTaskIsApproved($task);

        $wallet = $this->walletRepository->findByUserId($userId);
        $this->assertEquals(PointsMother::high()->value(), $wallet->balance()->value());
    }

    public function testRepositoryCanFindPendingTasks(): void
    {
        // Note: This test is kept for backward compatibility with repository method
        // Tasks are now created with NEW status, not PENDING
        // Given - Create multiple tasks with different statuses
        $adminId = UuidMother::random();
        $teamId = $this->createTeamWithAdmin($adminId);

        $newTaskId = UuidMother::random();
        $completedTaskId = UuidMother::random();

        $newCommand = new CreateTaskCommand(
            $newTaskId->value(),
            TaskNameMother::create()->value(),
            'New task',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            null
        );
        ($this->createHandler)($newCommand);

        $completedCommand = new CreateTaskCommand(
            $completedTaskId->value(),
            TaskNameMother::create()->value(),
            'To be completed',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            null
        );
        ($this->createHandler)($completedCommand);
        ($this->completeHandler)(new CompleteTaskCommand($completedTaskId->value(), UuidMother::random()->value()));

        // When - findPending returns empty (tasks are NEW, not PENDING)
        $pendingTasks = $this->taskRepository->findPending();

        // Then
        $this->assertCount(0, $pendingTasks);
    }

    public function testRepositoryCanFindCompletedTasks(): void
    {
        // Given
        $adminId = UuidMother::random();
        $teamId = $this->createTeamWithAdmin($adminId);

        $taskId = UuidMother::random();
        $createCommand = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create()->value(),
            'Task',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            null
        );
        ($this->createHandler)($createCommand);
        ($this->completeHandler)(new CompleteTaskCommand($taskId->value(), UuidMother::random()->value()));

        // When
        $completedTasks = $this->taskRepository->findCompleted();

        // Then
        $this->assertCount(1, $completedTasks);
        TaskAssert::assertTaskIsCompleted($completedTasks[0]);
    }

    public function testRepositoryCanFindTasksByAssignedUser(): void
    {
        // Given
        $adminId = UuidMother::random();
        $teamId = $this->createTeamWithAdmin($adminId);

        $userId = UuidMother::random();
        $taskId1 = UuidMother::random();
        $taskId2 = UuidMother::random();

        $command1 = new CreateTaskCommand(
            $taskId1->value(),
            TaskNameMother::create()->value(),
            'Task 1',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            $userId->value()
        );
        ($this->createHandler)($command1);

        $command2 = new CreateTaskCommand(
            $taskId2->value(),
            TaskNameMother::create()->value(),
            'Task 2',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            $userId->value()
        );
        ($this->createHandler)($command2);

        $command3 = new CreateTaskCommand(
            UuidMother::random()->value(),
            TaskNameMother::create()->value(),
            'Task 3',
            PointsMother::medium()->value(),
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            UuidMother::random()->value() // Different user
        );
        ($this->createHandler)($command3);

        // When
        $userTasks = $this->taskRepository->findByAssignedUser($userId);

        // Then
        $this->assertCount(2, $userTasks);
    }
}
