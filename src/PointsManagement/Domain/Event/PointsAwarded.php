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
        public DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
