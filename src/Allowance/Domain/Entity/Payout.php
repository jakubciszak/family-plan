<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Entity;

use App\Allowance\Domain\Event\PayoutOffered;
use App\Allowance\Domain\ValueObject\Money;
use App\Allowance\Domain\ValueObject\PayoutStatus;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cash handed over: an adult offers all or part of what is waiting, the owner confirms they got it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'allowance_payouts')]
#[ORM\Index(columns: ['user_id', 'status'])]
class Payout
{
    /**
     * @var list<object>
     */
    private array $domainEvents = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $userId,

        #[ORM\Column(type: 'bigint')]
        private int $amount,

        #[ORM\Column(type: 'string', length: 30, enumType: PayoutStatus::class)]
        private PayoutStatus $status,

        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $note,

        #[ORM\Column(type: 'uuid')]
        private Uuid $offeredBy,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $offeredAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $settledAt = null,

        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $transactionId = null
    ) {
    }

    public static function offer(
        Uuid $id,
        Uuid $userId,
        Money $amount,
        Uuid $offeredBy,
        ?string $note,
        ClockInterface $clock
    ): self {
        $amount->assertPositive('A payout');

        $offeredAt = $clock->now();

        $payout = new self(
            $id,
            $userId,
            $amount->minorUnits(),
            PayoutStatus::AWAITING_CONFIRMATION,
            $note,
            $offeredBy,
            $offeredAt
        );

        $payout->domainEvents[] = new PayoutOffered(
            $id,
            $userId,
            $amount->minorUnits(),
            $offeredBy,
            $offeredAt
        );

        return $payout;
    }

    /**
     * @return list<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    public function confirm(Uuid $transactionId, ClockInterface $clock): void
    {
        $this->assertAwaiting();

        $this->status = PayoutStatus::CONFIRMED;
        $this->transactionId = $transactionId;
        $this->settledAt = $clock->now();
    }

    public function cancel(ClockInterface $clock): void
    {
        $this->assertAwaiting();

        $this->status = PayoutStatus::CANCELLED;
        $this->settledAt = $clock->now();
    }

    public function isAwaitingConfirmation(): bool
    {
        return $this->status === PayoutStatus::AWAITING_CONFIRMATION;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function amount(): Money
    {
        return Money::fromMinorUnits((int) $this->amount);
    }

    public function status(): PayoutStatus
    {
        return $this->status;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function offeredBy(): Uuid
    {
        return $this->offeredBy;
    }

    public function offeredAt(): DateTimeImmutable
    {
        return $this->offeredAt;
    }

    public function settledAt(): ?DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function transactionId(): ?Uuid
    {
        return $this->transactionId;
    }

    private function assertAwaiting(): void
    {
        if (!$this->isAwaitingConfirmation()) {
            throw new \DomainException('This payout has already been settled');
        }
    }
}
