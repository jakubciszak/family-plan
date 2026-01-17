<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Application;

use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRelationshipRepository;
use App\Party\Infrastructure\Persistence\InMemory\InMemoryPartyRepository;
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
use App\Tests\TaskManagement\Assert\TaskAssert;
use App\Tests\TaskManagement\Mother\FrequencyMother;
use App\Tests\TaskManagement\Mother\PointsMother;
use App\Tests\TaskManagement\Mother\TaskNameMother;
use App\Tests\UserManagement\Mother\UserMother;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for complete task approval workflow with points awarding
 * Tests the interaction between TaskManagement and PointsManagement contexts
 *
 * MIGRATED TO PARTY ARCHETYPE
 */
class TaskApprovalWithPointsIntegrationTest extends TestCase
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

    public function testCompleteTaskApprovalWorkflowWithPointsAwarding(): void
    {
        // Given - Create admin and regular user
        $adminId = UuidMother::random();
        $userId = UuidMother::random();
        
        $admin = UserMother::aUser()
            ->withId($adminId)
            ->asAdmin()
            ->build();
        $this->userRepository->save($admin);
        
        $user = UserMother::aUser()
            ->withId($userId)
            ->asRegularUser()
            ->build();
        $this->userRepository->save($user);
        
        // And - Create a task worth 100 points
        $taskId = UuidMother::random();
        $taskPoints = 100;
        
        $teamId = $this->createTeamWithAdmin($adminId);
        
        $createCommand = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create('Complete important task')->value(),
            'Very important task',
            $taskPoints,
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            $userId->value()
        );
        ($this->createHandler)($createCommand);
        
        // When - User completes the task
        $completeCommand = new CompleteTaskCommand($taskId->value(), $userId->value());
        ($this->completeHandler)($completeCommand);
        
        // Then - Task is marked as completed
        $task = $this->taskRepository->findById($taskId);
        TaskAssert::assertTaskIsCompleted($task);
        
        // And - User has no wallet yet
        $walletBefore = $this->walletRepository->findByUserId($userId);
        $this->assertNull($walletBefore);
        
        // When - Admin approves the task
        $approveCommand = new ApproveTaskCommand($taskId->value(), $adminId->value());
        ($this->approveHandler)($approveCommand);
        
        // Then - Task is approved
        $approvedTask = $this->taskRepository->findById($taskId);
        TaskAssert::assertTaskIsApproved($approvedTask);
        
        // And - User wallet is created with awarded points
        $wallet = $this->walletRepository->findByUserId($userId);
        $this->assertNotNull($wallet);
        $this->assertEquals($taskPoints, $wallet->balance()->value());
    }

    public function testMultipleTaskApprovalsAccumulatePoints(): void
    {
        // Given - Admin and user
        $adminId = UuidMother::random();
        $userId = UuidMother::random();
        
        $admin = UserMother::aUser()->withId($adminId)->asAdmin()->build();
        $user = UserMother::aUser()->withId($userId)->asRegularUser()->build();
        
        $this->userRepository->save($admin);
        $this->userRepository->save($user);
        
        // When - Create and approve first task (50 points)
        $task1Id = UuidMother::random();
        $this->createAndApproveTask($task1Id, $userId, $adminId, 50);
        
        // And - Create and approve second task (30 points)
        $task2Id = UuidMother::random();
        $this->createAndApproveTask($task2Id, $userId, $adminId, 30);
        
        // And - Create and approve third task (20 points)
        $task3Id = UuidMother::random();
        $this->createAndApproveTask($task3Id, $userId, $adminId, 20);
        
        // Then - Points accumulate to 100
        $wallet = $this->walletRepository->findByUserId($userId);
        $this->assertEquals(100, $wallet->balance()->value());
    }

    public function testNonAdminCannotApproveTask(): void
    {
        // Given - Two regular users
        $userId1 = UuidMother::random();
        $userId2 = UuidMother::random();
        
        $user1 = UserMother::aUser()->withId($userId1)->asRegularUser()->build();
        $user2 = UserMother::aUser()->withId($userId2)->asRegularUser()->build();
        
        $this->userRepository->save($user1);
        $this->userRepository->save($user2);
        
        // And - User1 creates team and task
        $teamId = $this->createTeamWithAdmin($userId1);

        $taskId = UuidMother::random();
        $createCommand = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create()->value(),
            'Description',
            50,
            FrequencyMother::daily()->value,
            $userId1->value(),
            $teamId->value(),
            $userId1->value()
        );
        ($this->createHandler)($createCommand);
        ($this->completeHandler)(new CompleteTaskCommand($taskId->value(), $userId1->value()));

        // When/Then - User2 (non-admin) tries to approve
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only administrators can approve tasks');

        $approveCommand = new ApproveTaskCommand($taskId->value(), $userId2->value());
        ($this->approveHandler)($approveCommand);
    }

    public function testAdminApprovalCreatesWalletIfNotExists(): void
    {
        // Given - Admin and user without wallet
        $adminId = UuidMother::random();
        $userId = UuidMother::random();
        
        $admin = UserMother::aUser()->withId($adminId)->asAdmin()->build();
        $user = UserMother::aUser()->withId($userId)->asRegularUser()->build();
        
        $this->userRepository->save($admin);
        $this->userRepository->save($user);
        
        // Verify no wallet exists
        $this->assertNull($this->walletRepository->findByUserId($userId));
        
        // When - Task is created, completed, and approved
        $taskId = UuidMother::random();
        $this->createAndApproveTask($taskId, $userId, $adminId, 75);
        
        // Then - Wallet is automatically created with points
        $wallet = $this->walletRepository->findByUserId($userId);
        $this->assertNotNull($wallet);
        $this->assertEquals(75, $wallet->balance()->value());
    }

    public function testDifferentUsersHaveSeparateWallets(): void
    {
        // Given - Admin and two users
        $adminId = UuidMother::random();
        $user1Id = UuidMother::random();
        $user2Id = UuidMother::random();
        
        $admin = UserMother::aUser()->withId($adminId)->asAdmin()->build();
        $user1 = UserMother::aUser()->withId($user1Id)->asRegularUser()->build();
        $user2 = UserMother::aUser()->withId($user2Id)->asRegularUser()->build();
        
        $this->userRepository->save($admin);
        $this->userRepository->save($user1);
        $this->userRepository->save($user2);
        
        // When - User1 completes tasks worth 100 points
        $this->createAndApproveTask(UuidMother::random(), $user1Id, $adminId, 60);
        $this->createAndApproveTask(UuidMother::random(), $user1Id, $adminId, 40);
        
        // And - User2 completes tasks worth 50 points
        $this->createAndApproveTask(UuidMother::random(), $user2Id, $adminId, 30);
        $this->createAndApproveTask(UuidMother::random(), $user2Id, $adminId, 20);
        
        // Then - Each user has their own wallet with correct balance
        $wallet1 = $this->walletRepository->findByUserId($user1Id);
        $wallet2 = $this->walletRepository->findByUserId($user2Id);
        
        $this->assertEquals(100, $wallet1->balance()->value());
        $this->assertEquals(50, $wallet2->balance()->value());
    }

    private function createAndApproveTask($taskId, $userId, $adminId, int $points): void
    {
        $teamId = $this->createTeamWithAdmin($adminId);

        $createCommand = new CreateTaskCommand(
            $taskId->value(),
            TaskNameMother::create()->value(),
            'Task description',
            $points,
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            $userId->value()
        );
        ($this->createHandler)($createCommand);

        ($this->completeHandler)(new CompleteTaskCommand($taskId->value(), $userId->value()));
        ($this->approveHandler)(new ApproveTaskCommand($taskId->value(), $adminId->value()));
    }
}
