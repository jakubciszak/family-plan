<?php

declare(strict_types=1);

namespace App\UserManagement\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\ValueObject\Email;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findById(Uuid $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function findAll(): array;

    public function delete(User $user): void;
}
