<?php

declare(strict_types=1);

namespace App\UserManagement\Domain\ValueObject;

use InvalidArgumentException;

enum Role: string
{
    case USER = 'ROLE_USER';
    case ADMIN = 'ROLE_ADMIN';

    public static function fromString(string $value): self
    {
        return match($value) {
            'ROLE_USER', 'USER' => self::USER,
            'ROLE_ADMIN', 'ADMIN' => self::ADMIN,
            default => throw new InvalidArgumentException(sprintf('Invalid role: %s', $value))
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isUser(): bool
    {
        return $this === self::USER;
    }
}
