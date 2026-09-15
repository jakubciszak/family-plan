<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Entity;

use App\Allowance\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'allowance_entries')]
#[ORM\Index(columns: ['account_id', 'booked_at'])]
#[ORM\Index(columns: ['transaction_id'])]
class MoneyEntry
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $transactionId,

        #[ORM\Column(type: 'uuid')]
        private Uuid $accountId,

        #[ORM\Column(type: 'bigint')]
        private int $amount,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $bookedAt
    ) {
    }

    public static function record(
        Uuid $id,
        Uuid $transactionId,
        Uuid $accountId,
        Money $amount,
        DateTimeImmutable $bookedAt
    ): self {
        if ($amount->isZero()) {
            throw new \DomainException('An entry cannot be for nothing');
        }

        return new self($id, $transactionId, $accountId, $amount->minorUnits(), $bookedAt);
    }

    public function belongsTo(Uuid $accountId): bool
    {
        return $this->accountId->equals($accountId);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function transactionId(): Uuid
    {
        return $this->transactionId;
    }

    public function accountId(): Uuid
    {
        return $this->accountId;
    }

    public function amount(): Money
    {
        return Money::fromMinorUnits((int) $this->amount);
    }

    public function bookedAt(): DateTimeImmutable
    {
        return $this->bookedAt;
    }
}
