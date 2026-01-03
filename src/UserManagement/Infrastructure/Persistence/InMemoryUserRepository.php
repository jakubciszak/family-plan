<?php

declare(strict_types=1);

namespace App\UserManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->id()->value()] = $user;
    }

    public function findById(Uuid $id): ?User
    {
        return $this->users[$id->value()] ?? null;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email()->equals($email)) {
                return $user;
            }
        }

        return null;
    }

    public function findAll(): array
    {
        return array_values($this->users);
    }

    public function findAdmins(): array
    {
        return array_values(array_filter(
            $this->users,
            fn(User $user) => $user->role()->value === 'admin'
        ));
    }

    public function delete(User $user): void
    {
        unset($this->users[$user->id()->value()]);
    }

    public function clear(): void
    {
        $this->users = [];
    }
}
