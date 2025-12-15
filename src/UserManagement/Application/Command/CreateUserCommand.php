<?php

declare(strict_types=1);

namespace App\UserManagement\Application\Command;

final readonly class CreateUserCommand
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $password,
        public string $role
    ) {
    }
}
