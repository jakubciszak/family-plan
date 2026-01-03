<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Application;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;
use App\Tests\Notifications\Mother\NotificationChannelMother;
use App\Tests\Notifications\Mother\NotificationMessageMother;
use App\Tests\Notifications\Mother\RecipientMother;
use PHPUnit\Framework\TestCase;

/**
 * Integration test demonstrating how other bounded contexts 
 * can use the Notifications context as an open-host service
 */
class NotificationIntegrationTest extends TestCase
{
    private InMemoryNotificationAdapter $adapter;
    private NotificationFacade $notificationService;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryNotificationAdapter();
        $this->notificationService = new NotificationFacade([$this->adapter]);
    }

    public function testTaskManagementContextCanSendTaskApprovalEmail(): void
    {
        // Simulate TaskManagement context sending notification when task is approved
        $this->notificationService->sendEmail(
            'user@example.com',
            'Your task "Clean kitchen" has been approved! You earned 50 points.',
            'Task Approved'
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertEquals('user@example.com', $sent[0]['recipient']);
        $this->assertStringContainsString('approved', $sent[0]['message']);
    }

    public function testTaskManagementContextCanSendTaskAssignmentSms(): void
    {
        // Simulate TaskManagement context sending SMS when task is assigned
        $this->notificationService->sendSms(
            '+48123456789',
            'New task assigned: Clean kitchen. Due today.'
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertEquals('+48123456789', $sent[0]['recipient']);
        $this->assertStringContainsString('New task', $sent[0]['message']);
    }

    public function testPointsManagementContextCanSendPointsNotification(): void
    {
        // Simulate PointsManagement context sending notification about points earned
        $this->notificationService->sendEmail(
            'user@example.com',
            'Congratulations! You earned 100 bonus points for completing 7 tasks in a row.',
            'Bonus Points Earned',
            ['points' => 100, 'streak' => 7]
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertEquals(100, $sent[0]['parameters']['points']);
        $this->assertEquals(7, $sent[0]['parameters']['streak']);
    }

    public function testUserManagementContextCanSendWelcomeEmail(): void
    {
        // Simulate UserManagement context sending welcome email to new user
        $this->notificationService->sendEmail(
            'newuser@example.com',
            'Welcome to Family Plan! Start organizing your family tasks today.',
            'Welcome to Family Plan'
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertEquals('newuser@example.com', $sent[0]['recipient']);
        $this->assertStringContainsString('Welcome', $sent[0]['message']);
    }

    public function testCanSendMultipleNotificationsFromDifferentContexts(): void
    {
        // TaskManagement sends email
        $this->notificationService->sendEmail(
            'user1@example.com',
            'Task completed',
            'Task Status'
        );

        // PointsManagement sends SMS
        $this->notificationService->sendSms(
            '+48111222333',
            'You earned 50 points!'
        );

        // UserManagement sends email
        $this->notificationService->sendEmail(
            'user2@example.com',
            'Your account has been updated',
            'Account Update'
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(3, $sent);
    }

    public function testUsingMotherClassesForTestData(): void
    {
        // Demonstrate using Mother classes for test data
        $recipient = RecipientMother::email('test@example.com');
        $message = NotificationMessageMother::email('Test Subject');
        $channel = NotificationChannelMother::email();

        $this->notificationService->send(
            $channel->value(),
            $recipient->value(),
            $message->content(),
            $message->subject()
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
    }
}
