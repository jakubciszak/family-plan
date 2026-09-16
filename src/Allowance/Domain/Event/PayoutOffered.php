<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final readonly class PayoutOffered implements DomainEvent
{
    public function __construct(
        private Uuid $payoutId,
        private Uuid $userId,
        private int $amount,
        private Uuid $offeredBy,
        private DateTimeImmutable $offeredAt
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

    public function amount(): int
    {
        return $this->amount;
    }

    public function offeredBy(): Uuid
    {
        return $this->offeredBy;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->offeredAt;
    }

    public function eventName(): string
    {
        return 'allowance_payout.offered';
    }

    public function toPrimitives(): array
    {
        return [
            'payout_id' => $this->payoutId->value(),
            'user_id' => $this->userId->value(),
            'amount' => $this->amount,
            'offered_by' => $this->offeredBy->value(),
            'offered_at' => $this->offeredAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
