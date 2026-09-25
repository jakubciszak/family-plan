<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * A payout stopped waiting: the child confirmed the money arrived, or the parent cancelled it.
 */
final readonly class PayoutSettled implements DomainEvent
{
    public const CONFIRMED = 'confirmed';
    public const CANCELLED = 'cancelled';

    public function __construct(
        private Uuid $payoutId,
        private Uuid $userId,
        private string $outcome,
        private DateTimeImmutable $settledAt
    ) {
    }

    public function payoutId(): Uuid
    {
        return $this->payoutId;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function outcome(): string
    {
        return $this->outcome;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function eventName(): string
    {
        return 'allowance_payout.settled';
    }

    public function toPrimitives(): array
    {
        return [
            'payout_id' => $this->payoutId->value(),
            'user_id' => $this->userId->value(),
            'outcome' => $this->outcome,
            'settled_at' => $this->settledAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
