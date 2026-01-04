<?php

declare(strict_types=1);

namespace App\UserManagement\Application\Command;

final readonly class RegisterUserCommand
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $password,
        public ?string $phoneNumber = null
    ) {
    }
}
