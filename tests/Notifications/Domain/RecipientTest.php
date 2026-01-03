<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Domain;

use App\Notifications\Domain\ValueObject\Recipient;
use PHPUnit\Framework\TestCase;

class RecipientTest extends TestCase
{
    public function testCanCreateEmailRecipient(): void
    {
        $recipient = Recipient::email('test@example.com');
        
        $this->assertEquals('test@example.com', $recipient->value());
        $this->assertTrue($recipient->isEmail());
        $this->assertFalse($recipient->isPhoneNumber());
    }

    public function testCanCreatePhoneRecipient(): void
    {
        $recipient = Recipient::phoneNumber('+48123456789');
        
        $this->assertEquals('+48123456789', $recipient->value());
        $this->assertTrue($recipient->isPhoneNumber());
        $this->assertFalse($recipient->isEmail());
    }

    public function testThrowsExceptionForInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');
        
        Recipient::email('invalid-email');
    }

    public function testThrowsExceptionForEmptyEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        Recipient::email('');
    }

    public function testThrowsExceptionForEmptyPhoneNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Phone number cannot be empty');
        
        Recipient::phoneNumber('');
    }

    public function testAcceptsVariousPhoneNumberFormats(): void
    {
        $validPhones = [
            '+48123456789',
            '123456789',
            '+1-555-123-4567',
            '(555) 123-4567',
        ];
        
        foreach ($validPhones as $phone) {
            $recipient = Recipient::phoneNumber($phone);
            $this->assertEquals($phone, $recipient->value());
        }
    }
}
