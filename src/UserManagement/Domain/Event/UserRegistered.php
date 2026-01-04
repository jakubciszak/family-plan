<?php

declare(strict_types=1);

namespace App\UserManagement\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\ValueObject\Email;
use DateTimeImmutable;

final readonly class UserRegistered implements DomainEvent
{
    public function __construct(
        private Uuid $userId,
        private Email $email,
        private string $name,
        private string $activationToken,
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

    public function name(): string
    {
        return $this->name;
    }

    public function activationToken(): string
    {
        return $this->activationToken;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'user.registered';
    }

    public function toPrimitives(): array
    {
        return [
            'user_id' => $this->userId->value(),
            'email' => $this->email->value(),
            'name' => $this->name,
            'activation_token' => $this->activationToken,
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
        ];
    }
}
