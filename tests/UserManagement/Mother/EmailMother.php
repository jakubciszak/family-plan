<?php

declare(strict_types=1);

namespace App\Tests\UserManagement\Mother;

use App\UserManagement\Domain\ValueObject\Email;

final class EmailMother
{
    public static function create(string $value = 'test@example.com'): Email
    {
        return Email::fromString($value);
    }

    public static function random(): Email
    {
        return Email::fromString('user' . rand(1000, 9999) . '@example.com');
    }

    public static function withDomain(string $domain): Email
    {
        return Email::fromString('user@' . $domain);
    }

    public static function admin(): Email
    {
        return Email::fromString('admin@example.com');
    }

    public static function user(): Email
    {
        return Email::fromString('user@example.com');
    }
}
