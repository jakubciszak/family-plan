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
        private DateTimeImmutable $occurredOn
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'user_wallet.created';
    }

    public function toPrimitives(): array
    {
        return [
            'wallet_id' => $this->walletId->value(),
            'user_id' => $this->userId->value(),
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
        ];
    }
}
