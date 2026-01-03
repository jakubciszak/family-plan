<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Application;

use App\Notifications\Application\Command\SendNotificationCommand;
use App\Notifications\Application\Handler\SendNotificationHandler;
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;
use PHPUnit\Framework\TestCase;

class SendNotificationHandlerTest extends TestCase
{
    private InMemoryNotificationAdapter $adapter;
    private SendNotificationHandler $handler;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryNotificationAdapter();
        $this->handler = new SendNotificationHandler([$this->adapter]);
    }

    public function testCanSendEmailNotification(): void
    {
        $command = new SendNotificationCommand(
            channel: 'email',
            recipient: 'test@example.com',
            message: 'Test message',
            subject: 'Test subject'
        );

        ($this->handler)($command);

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertEquals('test@example.com', $sent[0]['recipient']);
        $this->assertEquals('Test message', $sent[0]['message']);
        $this->assertEquals('Test subject', $sent[0]['subject']);
        $this->assertEquals('email', $sent[0]['channel']);
    }

    public function testCanSendSmsNotification(): void
    {
        $command = new SendNotificationCommand(
            channel: 'sms',
            recipient: '+48123456789',
            message: 'SMS content'
        );

        ($this->handler)($command);

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertEquals('+48123456789', $sent[0]['recipient']);
        $this->assertEquals('SMS content', $sent[0]['message']);
        $this->assertEquals('sms', $sent[0]['channel']);
    }

    public function testCanSendNotificationWithAdditionalParameters(): void
    {
        $command = new SendNotificationCommand(
            channel: 'email',
            recipient: 'test@example.com',
            message: 'Message with params',
            subject: 'Subject',
            additionalParameters: ['priority' => 'high', 'sender' => 'system']
        );

        ($this->handler)($command);

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertEquals(['priority' => 'high', 'sender' => 'system'], $sent[0]['parameters']);
    }

    public function testThrowsExceptionForInvalidChannel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid notification channel: unsupported');

        $command = new SendNotificationCommand(
            channel: 'unsupported',
            recipient: 'test@example.com',
            message: 'Message'
        );

        ($this->handler)($command);
    }
}
