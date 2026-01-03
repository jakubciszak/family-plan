<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Application;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;
use PHPUnit\Framework\TestCase;

class NotificationFacadeTest extends TestCase
{
    private InMemoryNotificationAdapter $adapter;
    private NotificationFacade $facade;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryNotificationAdapter();
        $this->facade = new NotificationFacade([$this->adapter]);
    }

    public function testCanSendEmailWithSimpleParameters(): void
    {
        $this->facade->sendEmail(
            'test@example.com',
            'Test message',
            'Test subject'
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertEquals('test@example.com', $sent[0]['recipient']);
        $this->assertEquals('Test message', $sent[0]['message']);
        $this->assertEquals('Test subject', $sent[0]['subject']);
    }

    public function testCanSendEmailWithAdditionalParameters(): void
    {
        $this->facade->sendEmail(
            'test@example.com',
            'Test message',
            'Test subject',
            ['priority' => 'high']
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertEquals(['priority' => 'high'], $sent[0]['parameters']);
    }

    public function testCanSendSmsWithSimpleParameters(): void
    {
        $this->facade->sendSms(
            '+48123456789',
            'SMS content'
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertEquals('+48123456789', $sent[0]['recipient']);
        $this->assertEquals('SMS content', $sent[0]['message']);
    }

    public function testCanSendSmsWithAdditionalParameters(): void
    {
        $this->facade->sendSms(
            '+48123456789',
            'SMS content',
            ['sender' => 'FamilyPlan']
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertEquals(['sender' => 'FamilyPlan'], $sent[0]['parameters']);
    }

    public function testCanSendNotificationWithGenericMethod(): void
    {
        $this->facade->send(
            'email',
            'test@example.com',
            'Generic message',
            'Generic subject'
        );

        $sent = $this->adapter->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertEquals('email', $sent[0]['channel']);
    }
}
