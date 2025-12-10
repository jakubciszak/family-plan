<?php

declare(strict_types=1);

namespace App\UserManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use App\UserManagement\Domain\Event\UserCreated;
use DateTimeImmutable;

class User
{
    private array $domainEvents = [];

    private function __construct(
        private Uuid $id,
        private string $name,
        private Email $email,
        private string $password,
        private Role $role,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $updatedAt = null
    ) {
    }

    public static function create(
        Uuid $id,
        string $name,
        Email $email,
        string $hashedPassword,
        Role $role
    ): self {
        $user = new self(
            $id,
            $name,
            $email,
            $hashedPassword,
            $role,
            new DateTimeImmutable()
        );

        $user->record(new UserCreated($id, $email, $role, new DateTimeImmutable()));

        return $user;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateEmail(Email $email): void
    {
        $this->email = $email;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function changePassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function promoteToAdmin(): void
    {
        $this->role = Role::ADMIN;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    private function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
