<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Mother;

use App\Notifications\Domain\ValueObject\Recipient;

final class RecipientMother
{
    public static function email(?string $email = null): Recipient
    {
        return Recipient::email($email ?? self::randomEmail());
    }

    public static function phoneNumber(?string $phone = null): Recipient
    {
        return Recipient::phoneNumber($phone ?? self::randomPhoneNumber());
    }

    public static function random(): Recipient
    {
        return random_int(0, 1) === 0 
            ? self::email() 
            : self::phoneNumber();
    }

    private static function randomEmail(): string
    {
        $domains = ['example.com', 'test.com', 'demo.com'];
        $name = 'user' . random_int(1000, 9999);
        $domain = $domains[array_rand($domains)];
        
        return "{$name}@{$domain}";
    }

    private static function randomPhoneNumber(): string
    {
        return '+48' . random_int(100000000, 999999999);
    }
}
