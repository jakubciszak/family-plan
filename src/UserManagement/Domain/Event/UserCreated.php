<?php

declare(strict_types=1);

namespace App\UserManagement\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use DateTimeImmutable;

final readonly class UserCreated implements DomainEvent
{
    public function __construct(
        private Uuid $userId,
        private Email $email,
        private Role $role,
        private DateTimeImmutable $occurredOn
    ) {
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'user.created';
    }

    public function toPrimitives(): array
    {
        return [
            'user_id' => $this->userId->value(),
            'email' => $this->email->value(),
            'role' => $this->role->value,
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
        ];
    }
}
