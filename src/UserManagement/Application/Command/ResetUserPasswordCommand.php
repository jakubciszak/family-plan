<?php

declare(strict_types=1);

namespace App\UserManagement\Application\Command;

final readonly class ResetUserPasswordCommand
{
    public const int MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        public string $userId,
        public string $newPassword
    ) {
    }
}
