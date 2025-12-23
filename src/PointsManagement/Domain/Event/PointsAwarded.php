<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Domain event fired when points are awarded to a user wallet
 */
final readonly class PointsAwarded implements DomainEvent
{
    public function __construct(
        public Uuid $walletId,
        public Uuid $userId,
        public int $points,
        public string $reason,
        private DateTimeImmutable $occurredOn
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'points.awarded';
    }

    public function toPrimitives(): array
    {
        return [
            'wallet_id' => $this->walletId->value(),
            'user_id' => $this->userId->value(),
            'points' => $this->points,
            'reason' => $this->reason,
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
        ];
    }
}
