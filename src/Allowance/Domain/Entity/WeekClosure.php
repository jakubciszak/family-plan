<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Entity;

use App\Allowance\Domain\ValueObject\Money;
use App\Allowance\Domain\ValueObject\Settlement;
use App\Allowance\Domain\ValueObject\WeekStart;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A closed week is settled once: nothing more can be booked into it and the amount it earned is kept as it was.
 */
#[ORM\Entity]
#[ORM\Table(name: 'allowance_week_closures')]
#[ORM\UniqueConstraint(name: 'uniq_allowance_closure_user_week', columns: ['user_id', 'week_start'])]
#[ORM\Index(columns: ['team_id', 'week_start'])]
class WeekClosure
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $teamId,

        #[ORM\Column(type: 'uuid')]
        private Uuid $userId,

        #[ORM\Column(type: 'date_immutable')]
        private DateTimeImmutable $weekStart,

        #[ORM\Column(type: 'json')]
        private array $breakdown,

        #[ORM\Column(type: 'bigint')]
        private int $total,

        #[ORM\Column(type: 'uuid')]
        private Uuid $closedBy,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $closedAt,

        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $transactionId = null
    ) {
    }

    public static function close(
        Uuid $id,
        Uuid $teamId,
        Uuid $userId,
        WeekStart $week,
        Settlement $settlement,
        Uuid $closedBy,
        ?Uuid $transactionId,
        ClockInterface $clock
    ): self {
        return new self(
            $id,
            $teamId,
            $userId,
            $week->monday(),
            $settlement->toArray(),
            $settlement->total()->minorUnits(),
            $closedBy,
            $clock->now(),
            $transactionId
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function teamId(): Uuid
    {
        return $this->teamId;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function week(): WeekStart
    {
        return WeekStart::of($this->weekStart);
    }

    public function breakdown(): array
    {
        return $this->breakdown;
    }

    public function total(): Money
    {
        return Money::fromMinorUnits((int) $this->total);
    }

    public function closedBy(): Uuid
    {
        return $this->closedBy;
    }

    public function closedAt(): DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function transactionId(): ?Uuid
    {
        return $this->transactionId;
    }
}
