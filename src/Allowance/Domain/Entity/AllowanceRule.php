<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Entity;

use App\Allowance\Domain\ValueObject\ConversionRate;
use App\Allowance\Domain\ValueObject\Money;
use App\PointsManagement\Domain\ValueObject\AccountKind as PointsAccountKind;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * What a week of points on one points account is worth in money, for a whole team.
 */
#[ORM\Entity]
#[ORM\Table(name: 'allowance_rules')]
#[ORM\UniqueConstraint(name: 'uniq_allowance_rule_team_account', columns: ['team_id', 'points_account'])]
#[ORM\Index(columns: ['team_id'])]
class AllowanceRule
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $teamId,

        #[ORM\Column(type: 'string', length: 30, enumType: PointsAccountKind::class)]
        private PointsAccountKind $pointsAccount,

        #[ORM\Column(type: 'integer')]
        private int $minimumPoints,

        #[ORM\Column(type: 'bigint')]
        private int $rateAmount,

        #[ORM\Column(type: 'integer')]
        private int $ratePerPoints,

        #[ORM\Column(type: 'boolean')]
        private bool $isActive,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $updatedAt = null
    ) {
    }

    public static function define(
        Uuid $id,
        Uuid $teamId,
        PointsAccountKind $pointsAccount,
        int $minimumPoints,
        ConversionRate $rate,
        ClockInterface $clock
    ): self {
        self::assertMinimum($minimumPoints);

        return new self(
            $id,
            $teamId,
            $pointsAccount,
            $minimumPoints,
            $rate->amount()->minorUnits(),
            $rate->perPoints(),
            true,
            $clock->now()
        );
    }

    public function adjust(int $minimumPoints, ConversionRate $rate, ClockInterface $clock): void
    {
        self::assertMinimum($minimumPoints);

        $this->minimumPoints = $minimumPoints;
        $this->rateAmount = $rate->amount()->minorUnits();
        $this->ratePerPoints = $rate->perPoints();
        $this->updatedAt = $clock->now();
    }

    public function activate(ClockInterface $clock): void
    {
        $this->isActive = true;
        $this->updatedAt = $clock->now();
    }

    public function deactivate(ClockInterface $clock): void
    {
        $this->isActive = false;
        $this->updatedAt = $clock->now();
    }

    /**
     * Points below the minimum earn nothing at all - the minimum is a gate, not a free allowance.
     */
    public function earnedOn(int $points): Money
    {
        if (!$this->isActive || $points < $this->minimumPoints) {
            return Money::zero();
        }

        return $this->rate()->convert($points);
    }

    public function rate(): ConversionRate
    {
        return ConversionRate::of(Money::fromMinorUnits((int) $this->rateAmount), $this->ratePerPoints);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function teamId(): Uuid
    {
        return $this->teamId;
    }

    public function pointsAccount(): PointsAccountKind
    {
        return $this->pointsAccount;
    }

    public function minimumPoints(): int
    {
        return $this->minimumPoints;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function assertMinimum(int $minimumPoints): void
    {
        if ($minimumPoints < 0) {
            throw new \InvalidArgumentException('A minimum cannot ask for fewer than no points');
        }
    }
}
