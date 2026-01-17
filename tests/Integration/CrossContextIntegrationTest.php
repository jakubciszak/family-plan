<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\PointsManagement\Infrastructure\Persistence\InMemoryUserWalletRepository;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Application\Command\CompleteTaskCommand;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Application\Handler\ApproveTaskHandler;
use App\TaskManagement\Application\Handler\CompleteTaskHandler;
use App\TaskManagement\Application\Handler\CreateTaskHandler;
use App\TaskManagement\Domain\Policy\AdminApprovalPolicy;
use App\TaskManagement\Domain\Strategy\TaskApprovalPointsAwardStrategy;
use App\TaskManagement\Infrastructure\Persistence\InMemoryTaskRepository;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\TaskManagement\Mother\FrequencyMother;
use App\Tests\TaskManagement\Mother\TaskNameMother;
use App\UserManagement\Domain\ValueObject\Email;
use App\Tests\UserManagement\Mother\UserMother;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Cross-context integration test
 * Tests the complete workflow across UserManagement, TaskManagement, and PointsManagement contexts
 */
class CrossContextIntegrationTest extends TestCase
{
    private InMemoryUserRepository $userRepository;
    private InMemoryTaskRepository $taskRepository;
    private InMemoryUserWalletRepository $walletRepository;
    private \App\TeamManagement\Infrastructure\Persistence\InMemoryTeamRepository $teamRepository;
    private \App\TeamManagement\Infrastructure\Persistence\InMemoryTeamMemberRepository $teamMemberRepository;
    private FixedClock $clock;

    private CreateTaskHandler $createTaskHandler;
    private CompleteTaskHandler $completeTaskHandler;
    private ApproveTaskHandler $approveTaskHandler;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->taskRepository = new InMemoryTaskRepository();
        $this->walletRepository = new InMemoryUserWalletRepository();
        $this->teamRepository = new \App\TeamManagement\Infrastructure\Persistence\InMemoryTeamRepository();
        $this->teamMemberRepository = new \App\TeamManagement\Infrastructure\Persistence\InMemoryTeamMemberRepository();
        $this->clock = new FixedClock();

        $this->createTaskHandler = new CreateTaskHandler($this->taskRepository, $this->teamMemberRepository);
        $this->completeTaskHandler = new CompleteTaskHandler($this->taskRepository);

        $approvalPolicy = new AdminApprovalPolicy($this->userRepository);
        $pointsStrategy = new TaskApprovalPointsAwardStrategy($this->walletRepository, $this->clock);

        $this->approveTaskHandler = new ApproveTaskHandler(
            $this->taskRepository,
            $approvalPolicy,
            $pointsStrategy
        );
    }

    private function createTeamWithAdmin(\App\Shared\Domain\ValueObject\Uuid $adminId): \App\Shared\Domain\ValueObject\Uuid
    {
        $teamId = UuidMother::random();
        $team = \App\TeamManagement\Domain\Entity\Team::create(
            $teamId,
            \App\TeamManagement\Domain\ValueObject\TeamName::fromString('Test Team'),
            'Test description',
            $adminId
        );
        $this->teamRepository->save($team);

        $teamMember = \App\TeamManagement\Domain\Entity\TeamMember::create(
            UuidMother::random(),
            $teamId,
            $adminId,
            \App\TeamManagement\Domain\ValueObject\TeamRole::admin()
        );
        $this->teamMemberRepository->save($teamMember);

        return $teamId;
    }

    public function testCompleteUserJourneyFromRegistrationToPointsRedemption(): void
    {
        // Phase 1: User Management - Create users
        $adminId = UuidMother::random();
        $userId = UuidMother::random();
        
        $admin = UserMother::aUser()
            ->withId($adminId)
            ->withName('System Admin')
            ->withEmail(Email::fromString('admin@familyplan.com'))
            ->asAdmin()
            ->build();
        
        $user = UserMother::aUser()
            ->withId($userId)
            ->withName('John Doe')
            ->withEmail(Email::fromString('john@familyplan.com'))
            ->asRegularUser()
            ->build();
        
        $this->userRepository->save($admin);
        $this->userRepository->save($user);
        
        // Verify users are created
        $this->assertTrue($this->userRepository->findById($adminId)->isAdmin());
        $this->assertFalse($this->userRepository->findById($userId)->isAdmin());
        
        // Phase 2: Task Management - Create tasks
        $teamId = $this->createTeamWithAdmin($adminId);
        
        $task1Id = UuidMother::random();
        $task2Id = UuidMother::random();
        $task3Id = UuidMother::random();
        
        // Create Task 1: Daily chore (50 points)
        ($this->createTaskHandler)(new CreateTaskCommand(
            $task1Id->value(),
            TaskNameMother::create('Clean kitchen')->value(),
            'Wash dishes and wipe counters',
            50,
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            $userId->value()
        ));
        
        // Create Task 2: Weekly chore (100 points)
        ($this->createTaskHandler)(new CreateTaskCommand(
            $task2Id->value(),
            TaskNameMother::create('Vacuum house')->value(),
            'Vacuum all rooms',
            100,
            FrequencyMother::weekly()->value,
            $adminId->value(),
            $teamId->value(),
            $userId->value()
        ));
        
        // Create Task 3: Monthly chore (200 points)
        ($this->createTaskHandler)(new CreateTaskCommand(
            $task3Id->value(),
            TaskNameMother::create('Deep clean bathroom')->value(),
            'Complete bathroom cleaning',
            200,
            FrequencyMother::monthly()->value,
            $adminId->value(),
            $teamId->value(),
            $userId->value()
        ));
        
        // Verify tasks are created with NEW status
        $task1 = $this->taskRepository->findById($task1Id);
        $this->assertEquals('new', $task1->status()->value);
        
        // Phase 3: Task Completion - User completes tasks
        ($this->completeTaskHandler)(new CompleteTaskCommand($task1Id->value(), $userId->value()));
        ($this->completeTaskHandler)(new CompleteTaskCommand($task2Id->value(), $userId->value()));
        ($this->completeTaskHandler)(new CompleteTaskCommand($task3Id->value(), $userId->value()));
        
        // Verify tasks are completed
        $this->assertEquals('completed', $this->taskRepository->findById($task1Id)->status()->value);
        $this->assertEquals('completed', $this->taskRepository->findById($task2Id)->status()->value);
        $this->assertEquals('completed', $this->taskRepository->findById($task3Id)->status()->value);
        
        // Phase 4: Points Management - No wallet exists yet
        $this->assertNull($this->walletRepository->findByUserId($userId));
        
        // Phase 5: Task Approval - Admin approves tasks one by one
        ($this->approveTaskHandler)(new ApproveTaskCommand($task1Id->value(), $adminId->value()));
        
        // After first approval, wallet is created with 50 points
        $wallet = $this->walletRepository->findByUserId($userId);
        $this->assertNotNull($wallet);
        $this->assertEquals(50, $wallet->balance()->value());
        
        // Approve second task
        ($this->approveTaskHandler)(new ApproveTaskCommand($task2Id->value(), $adminId->value()));
        $wallet = $this->walletRepository->findByUserId($userId);
        $this->assertEquals(150, $wallet->balance()->value()); // 50 + 100
        
        // Approve third task
        ($this->approveTaskHandler)(new ApproveTaskCommand($task3Id->value(), $adminId->value()));
        $wallet = $this->walletRepository->findByUserId($userId);
        $this->assertEquals(350, $wallet->balance()->value()); // 50 + 100 + 200
        
        // Verify all tasks are approved
        $this->assertEquals('approved', $this->taskRepository->findById($task1Id)->status()->value);
        $this->assertEquals('approved', $this->taskRepository->findById($task2Id)->status()->value);
        $this->assertEquals('approved', $this->taskRepository->findById($task3Id)->status()->value);
        
        // Phase 6: Points Redemption - User spends points
        $wallet->deductPoints(100, 'Movie night reward', $this->clock);
        $this->walletRepository->save($wallet);
        
        // Final wallet balance
        $finalWallet = $this->walletRepository->findByUserId($userId);
        $this->assertEquals(250, $finalWallet->balance()->value()); // 350 - 100
    }

    public function testAdminPermissionsAreEnforcedAcrossContexts(): void
    {
        // Given - Admin and two regular users
        $adminId = UuidMother::random();
        $user1Id = UuidMother::random();
        $user2Id = UuidMother::random();
        
        $admin = UserMother::aUser()->withId($adminId)->asAdmin()->build();
        $user1 = UserMother::aUser()->withId($user1Id)->asRegularUser()->build();
        $user2 = UserMother::aUser()->withId($user2Id)->asRegularUser()->build();
        
        $this->userRepository->save($admin);
        $this->userRepository->save($user1);
        $this->userRepository->save($user2);
        
        $teamId = $this->createTeamWithAdmin($adminId);
        
        // And - Task created and completed by user1
        $taskId = UuidMother::random();
        ($this->createTaskHandler)(new CreateTaskCommand(
            $taskId->value(),
            'Task',
            'Description',
            50,
            'daily',
            $adminId->value(),
            $teamId->value(),
            $user1Id->value()
        ));
        ($this->completeTaskHandler)(new CompleteTaskCommand($taskId->value(), $user1Id->value()));
        
        // When/Then - User2 tries to approve (should fail)
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only administrators can approve tasks');
        
        ($this->approveTaskHandler)(new ApproveTaskCommand($taskId->value(), $user2Id->value()));
    }

    public function testMultipleUsersCanEarnPointsIndependently(): void
    {
        // Given - Admin and three users
        $adminId = UuidMother::random();
        $user1Id = UuidMother::random();
        $user2Id = UuidMother::random();
        $user3Id = UuidMother::random();
        
        $admin = UserMother::aUser()->withId($adminId)->asAdmin()->build();
        $user1 = UserMother::aUser()->withId($user1Id)->withName('Alice')->asRegularUser()->build();
        $user2 = UserMother::aUser()->withId($user2Id)->withName('Bob')->asRegularUser()->build();
        $user3 = UserMother::aUser()->withId($user3Id)->withName('Charlie')->asRegularUser()->build();
        
        $this->userRepository->save($admin);
        $this->userRepository->save($user1);
        $this->userRepository->save($user2);
        $this->userRepository->save($user3);
        
        $teamId = $this->createTeamWithAdmin($adminId);
        
        // When - Each user completes different tasks
        // Alice completes 2 tasks
        $this->createCompleteAndApproveTask($user1Id, $adminId, $teamId, 50);
        $this->createCompleteAndApproveTask($user1Id, $adminId, $teamId, 30);
        
        // Bob completes 1 task
        $this->createCompleteAndApproveTask($user2Id, $adminId, $teamId, 75);
        
        // Charlie completes 3 tasks
        $this->createCompleteAndApproveTask($user3Id, $adminId, $teamId, 25);
        $this->createCompleteAndApproveTask($user3Id, $adminId, $teamId, 40);
        $this->createCompleteAndApproveTask($user3Id, $adminId, $teamId, 35);
        
        // Then - Each user has their own wallet with correct balance
        $wallet1 = $this->walletRepository->findByUserId($user1Id);
        $wallet2 = $this->walletRepository->findByUserId($user2Id);
        $wallet3 = $this->walletRepository->findByUserId($user3Id);
        
        $this->assertEquals(80, $wallet1->balance()->value());  // 50 + 30
        $this->assertEquals(75, $wallet2->balance()->value());  // 75
        $this->assertEquals(100, $wallet3->balance()->value()); // 25 + 40 + 35
    }

    private function createCompleteAndApproveTask($userId, $adminId, $teamId, int $points): void
    {
        $taskId = UuidMother::random();
        
        ($this->createTaskHandler)(new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create()->value(),
            'Task description',
            $points,
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            $userId->value()
        ));
        
        ($this->completeTaskHandler)(new CompleteTaskCommand($taskId->value(), $userId->value()));
        ($this->approveTaskHandler)(new ApproveTaskCommand($taskId->value(), $adminId->value()));
    }
}
