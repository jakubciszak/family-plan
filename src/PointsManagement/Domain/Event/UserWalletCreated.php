<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Domain event fired when a user wallet is created
 */
final readonly class UserWalletCreated implements DomainEvent
{
    public function __construct(
        public Uuid $walletId,
        public Uuid $userId,
        public DateTimeImmutable $occurredAt
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
