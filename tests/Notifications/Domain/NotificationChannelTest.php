<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Domain;

use App\Notifications\Domain\ValueObject\NotificationChannel;
use PHPUnit\Framework\TestCase;

class NotificationChannelTest extends TestCase
{
    public function testCanCreateEmailChannel(): void
    {
        $channel = NotificationChannel::email();
        
        $this->assertEquals('email', $channel->value());
    }

    public function testCanCreateSmsChannel(): void
    {
        $channel = NotificationChannel::sms();
        
        $this->assertEquals('sms', $channel->value());
    }

    public function testCanCreateInAppChannel(): void
    {
        $channel = NotificationChannel::inApp();

        $this->assertEquals('in_app', $channel->value());
    }

    public function testFromStringCreatesCorrectChannel(): void
    {
        $emailChannel = NotificationChannel::fromString('email');
        $smsChannel = NotificationChannel::fromString('sms');
        $inAppChannel = NotificationChannel::fromString('in_app');
        
        $this->assertEquals('email', $emailChannel->value());
        $this->assertEquals('sms', $smsChannel->value());
        $this->assertEquals('in_app', $inAppChannel->value());
    }

    public function testThrowsExceptionForInvalidChannel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid notification channel: invalid');
        
        NotificationChannel::fromString('invalid');
    }

    public function testEmailChannelIsEmail(): void
    {
        $channel = NotificationChannel::email();
        
        $this->assertTrue($channel->isEmail());
        $this->assertFalse($channel->isSms());
        $this->assertFalse($channel->isInApp());
    }

    public function testInAppChannelIsInApp(): void
    {
        $channel = NotificationChannel::inApp();
        
        $this->assertTrue($channel->isInApp());
        $this->assertFalse($channel->isEmail());
        $this->assertFalse($channel->isSms());
    }

    public function testSmsChannelIsSms(): void
    {
        $channel = NotificationChannel::sms();
        
        $this->assertTrue($channel->isSms());
        $this->assertFalse($channel->isEmail());
        $this->assertFalse($channel->isInApp());
    }
}
