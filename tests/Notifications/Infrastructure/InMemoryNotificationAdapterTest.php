<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Infrastructure;

use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;
use PHPUnit\Framework\TestCase;

class InMemoryNotificationAdapterTest extends TestCase
{
    private InMemoryNotificationAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryNotificationAdapter();
    }

    public function testCanSendEmailNotification(): void
    {
        $recipient = Recipient::email('test@example.com');
        $message = NotificationMessage::create('Test message', 'Test subject');
        $channel = NotificationChannel::email();

        $this->adapter->send($recipient, $message, $channel);

        $sentNotifications = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sentNotifications);
        
        $sent = $sentNotifications[0];
        $this->assertEquals('test@example.com', $sent['recipient']);
        $this->assertEquals('Test message', $sent['message']);
        $this->assertEquals('Test subject', $sent['subject']);
        $this->assertEquals('email', $sent['channel']);
    }

    public function testCanSendSmsNotification(): void
    {
        $recipient = Recipient::phoneNumber('+48123456789');
        $message = NotificationMessage::create('SMS content');
        $channel = NotificationChannel::sms();

        $this->adapter->send($recipient, $message, $channel);

        $sentNotifications = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sentNotifications);
        
        $sent = $sentNotifications[0];
        $this->assertEquals('+48123456789', $sent['recipient']);
        $this->assertEquals('SMS content', $sent['message']);
        $this->assertEquals('sms', $sent['channel']);
    }

    public function testCanSendMultipleNotifications(): void
    {
        $this->adapter->send(
            Recipient::email('user1@example.com'),
            NotificationMessage::create('Message 1'),
            NotificationChannel::email()
        );

        $this->adapter->send(
            Recipient::phoneNumber('+48111222333'),
            NotificationMessage::create('Message 2'),
            NotificationChannel::sms()
        );

        $this->assertCount(2, $this->adapter->getSentNotifications());
    }

    public function testCanClearSentNotifications(): void
    {
        $this->adapter->send(
            Recipient::email('test@example.com'),
            NotificationMessage::create('Test'),
            NotificationChannel::email()
        );

        $this->assertCount(1, $this->adapter->getSentNotifications());

        $this->adapter->clear();
        
        $this->assertCount(0, $this->adapter->getSentNotifications());
    }
}
