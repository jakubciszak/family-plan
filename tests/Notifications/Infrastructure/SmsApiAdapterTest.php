<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Infrastructure;

use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use App\Notifications\Infrastructure\Adapter\SmsApiAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Log\Logger;

class SmsApiAdapterTest extends TestCase
{
    private const TEST_API_URL = 'https://api.smsapi.test';
    private const TEST_API_TOKEN = 'test-token-12345';

    public function testSupportsOnlySmsChannel(): void
    {
        $adapter = new SmsApiAdapter(self::TEST_API_URL, self::TEST_API_TOKEN);

        $this->assertTrue($adapter->supports(NotificationChannel::sms()));
        $this->assertFalse($adapter->supports(NotificationChannel::email()));
    }

    public function testThrowsExceptionWhenChannelIsNotSms(): void
    {
        $adapter = new SmsApiAdapter(self::TEST_API_URL, self::TEST_API_TOKEN);
        $recipient = Recipient::email('test@example.com');
        $message = NotificationMessage::create('Test message');
        $channel = NotificationChannel::email();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SmsApiAdapter only supports SMS channel');

        $adapter->send($recipient, $message, $channel);
    }

    public function testThrowsExceptionWhenRecipientIsNotPhoneNumber(): void
    {
        $adapter = new SmsApiAdapter(self::TEST_API_URL, self::TEST_API_TOKEN);
        $recipient = Recipient::email('test@example.com');
        $message = NotificationMessage::create('Test message');
        $channel = NotificationChannel::sms();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipient must be a phone number for SMS channel');

        $adapter->send($recipient, $message, $channel);
    }

    public function testSkipsSendingWhenTokenIsEmpty(): void
    {
        $log = fopen('php://memory', 'r+');
        $adapter = new SmsApiAdapter(self::TEST_API_URL, '', new Logger('debug', $log));
        $recipient = Recipient::phoneNumber('+48123456789');
        $message = NotificationMessage::create('Test SMS');
        $channel = NotificationChannel::sms();

        $adapter->send($recipient, $message, $channel);

        rewind($log);
        $this->assertStringContainsString('SMS API token not configured', stream_get_contents($log));
    }

    public function testConstructorAcceptsRequiredParameters(): void
    {
        $adapter = new SmsApiAdapter(self::TEST_API_URL, self::TEST_API_TOKEN);
        
        $this->assertInstanceOf(SmsApiAdapter::class, $adapter);
    }

    public function testConstructorAcceptsOptionalLogger(): void
    {
        $adapter = new SmsApiAdapter(self::TEST_API_URL, self::TEST_API_TOKEN, new Logger());

        $this->assertTrue($adapter->supports(NotificationChannel::sms()));
    }
}
