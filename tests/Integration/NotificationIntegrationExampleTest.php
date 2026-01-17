<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Infrastructure\Adapter\EmailNotificationAdapter;
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;
use App\Notifications\Infrastructure\Adapter\SmsNotificationAdapter;
use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Application\Command\CompleteTaskCommand;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Application\Handler\ApproveTaskHandler;
use App\TaskManagement\Application\Handler\CompleteTaskHandler;
use App\TaskManagement\Application\Handler\CreateTaskHandler;
use App\TaskManagement\Infrastructure\Persistence\InMemoryTaskRepository;
use App\TaskManagement\Domain\Policy\AdminApprovalPolicy;
use App\TaskManagement\Domain\Strategy\TaskApprovalPointsAwardStrategy;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use App\PointsManagement\Infrastructure\Persistence\InMemoryUserWalletRepository;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\TaskManagement\Mother\FrequencyMother;
use App\Tests\TaskManagement\Mother\PointsMother;
use App\Tests\TaskManagement\Mother\TaskNameMother;
use App\Tests\UserManagement\Mother\EmailMother;
use App\Tests\UserManagement\Mother\UserMother;
use PHPUnit\Framework\TestCase;

/**
 * Example integration test showing how TaskManagement context
 * could integrate with Notifications context to send notifications
 * when tasks are approved.
 * 
 * This is a demonstration only - actual integration would be implemented
 * through domain events or direct service calls.
 */
class NotificationIntegrationExampleTest extends TestCase
{
    private InMemoryTaskRepository $taskRepository;
    private InMemoryUserRepository $userRepository;
    private InMemoryUserWalletRepository $walletRepository;
    private \App\TeamManagement\Infrastructure\Persistence\InMemoryTeamRepository $teamRepository;
    private \App\TeamManagement\Infrastructure\Persistence\InMemoryTeamMemberRepository $teamMemberRepository;
    private InMemoryNotificationAdapter $notificationAdapter;
    private NotificationFacade $notificationService;
    private FixedClock $clock;
    private CreateTaskHandler $createHandler;
    private CompleteTaskHandler $completeHandler;
    private ApproveTaskHandler $approveHandler;

    protected function setUp(): void
    {
        $this->taskRepository = new InMemoryTaskRepository();
        $this->userRepository = new InMemoryUserRepository();
        $this->walletRepository = new InMemoryUserWalletRepository();
        $this->teamRepository = new \App\TeamManagement\Infrastructure\Persistence\InMemoryTeamRepository();
        $this->teamMemberRepository = new \App\TeamManagement\Infrastructure\Persistence\InMemoryTeamMemberRepository();
        $this->clock = new FixedClock();

        // Setup notification service
        $this->notificationAdapter = new InMemoryNotificationAdapter();
        $this->notificationService = new NotificationFacade([$this->notificationAdapter]);

        // Setup task handlers
        $this->createHandler = new CreateTaskHandler($this->taskRepository, $this->teamMemberRepository);
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

    public function testCanSendNotificationAfterTaskApproval(): void
    {
        // Given - Create and assign a user
        $userId = UuidMother::random();
        $user = UserMother::aUser()
            ->withId($userId)
            ->withEmail(EmailMother::create('user@example.com'))
            ->build();
        $this->userRepository->save($user);
        
        // Create an admin
        $adminId = UuidMother::random();
        $admin = UserMother::aUser()
            ->withId($adminId)
            ->asAdmin()
            ->build();
        $this->userRepository->save($admin);

        // Create team
        $teamId = $this->createTeamWithAdmin($adminId);
        
        // Create a task
        $taskId = UuidMother::random();
        $taskName = TaskNameMother::create('Clean kitchen');
        $points = PointsMother::medium();
        
        $createCommand = new CreateTaskCommand(
            $taskId->value(),
            $taskName->value(),
            'Wash dishes and wipe counters',
            $points->value(),
            FrequencyMother::daily()->value,
            $adminId->value(),
            $teamId->value(),
            $userId->value()
        );
        ($this->createHandler)($createCommand);

        // Complete the task
        $completeCommand = new CompleteTaskCommand($taskId->value(), $userId->value());
        ($this->completeHandler)($completeCommand);

        // Approve the task
        $approveCommand = new ApproveTaskCommand($taskId->value(), $adminId->value());
        ($this->approveHandler)($approveCommand);

        // When - Send notification about task approval
        // (In real implementation, this would be triggered by a domain event)
        $this->notificationService->sendEmail(
            recipient: $user->email()->value(),
            message: "Your task '{$taskName->value()}' has been approved! You earned {$points->value()} points.",
            subject: 'Task Approved',
            additionalParameters: [
                'taskId' => $taskId->value(),
                'taskName' => $taskName->value(),
                'points' => $points->value(),
            ]
        );

        // Then - Verify notification was sent
        $notifications = $this->notificationAdapter->getSentNotifications();
        $this->assertCount(1, $notifications);
        
        $notification = $notifications[0];
        $this->assertEquals('user@example.com', $notification['recipient']);
        $this->assertStringContainsString('approved', $notification['message']);
        $this->assertStringContainsString('Clean kitchen', $notification['message']);
        $this->assertEquals('Task Approved', $notification['subject']);
        $this->assertEquals($taskId->value(), $notification['parameters']['taskId']);
        $this->assertEquals($points->value(), $notification['parameters']['points']);
    }

    public function testCanSendSmsNotificationForTaskAssignment(): void
    {
        // Given
        $adminId = UuidMother::random();
        $userId = UuidMother::random();
        $taskId = UuidMother::random();
        $taskName = TaskNameMother::create('Clean kitchen');
        
        $teamId = $this->createTeamWithAdmin($adminId);

        $command = new CreateTaskCommand(
            $taskId->value(),
            $taskName->value(),
            'Weekly cleaning task',
            PointsMother::medium()->value(),
            FrequencyMother::weekly()->value,
            $adminId->value(),
            $teamId->value(),
            $userId->value()
        );
        
        ($this->createHandler)($command);

        // When - Send SMS about new task assignment
        $this->notificationService->sendSms(
            recipient: '+48123456789',
            message: "New task assigned: {$taskName->value()}",
            additionalParameters: [
                'taskId' => $taskId->value(),
                'userId' => $userId->value(),
            ]
        );

        // Then
        $notifications = $this->notificationAdapter->getSentNotifications();
        $this->assertCount(1, $notifications);
        
        $notification = $notifications[0];
        $this->assertEquals('+48123456789', $notification['recipient']);
        $this->assertStringContainsString('Clean kitchen', $notification['message']);
        $this->assertEquals($taskId->value(), $notification['parameters']['taskId']);
    }
}
