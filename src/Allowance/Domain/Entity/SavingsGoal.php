<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Entity;

use App\Allowance\Domain\ValueObject\Money;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Something the owner is saving up for. What is put aside sits on its own account until it is spent.
 */
#[ORM\Entity]
#[ORM\Table(name: 'allowance_goals')]
#[ORM\Index(columns: ['user_id'])]
class SavingsGoal
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $userId,

        #[ORM\Column(type: 'string', length: 120)]
        private string $name,

        #[ORM\Column(type: 'bigint')]
        private int $target,

        #[ORM\Column(type: 'date_immutable', nullable: true)]
        private ?DateTimeImmutable $wantedBy,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $reachedAt = null,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $closedAt = null
    ) {
    }

    public static function plan(
        Uuid $id,
        Uuid $userId,
        string $name,
        Money $target,
        ?DateTimeImmutable $wantedBy,
        ClockInterface $clock
    ): self {
        self::assertName($name);
        $target->assertPositive('A goal');

        return new self($id, $userId, trim($name), $target->minorUnits(), $wantedBy?->setTime(0, 0), $clock->now());
    }

    public function adjust(string $name, Money $target, ?DateTimeImmutable $wantedBy): void
    {
        self::assertName($name);
        $target->assertPositive('A goal');

        $this->name = trim($name);
        $this->target = $target->minorUnits();
        $this->wantedBy = $wantedBy?->setTime(0, 0);
    }

    public function noteProgress(Money $saved, ClockInterface $clock): void
    {
        $reached = !$this->target()->isGreaterThan($saved);

        if ($reached && $this->reachedAt === null) {
            $this->reachedAt = $clock->now();
        }

        if (!$reached) {
            $this->reachedAt = null;
        }
    }

    public function close(ClockInterface $clock): void
    {
        if ($this->closedAt !== null) {
            throw new \DomainException('This goal is already closed');
        }

        $this->closedAt = $clock->now();
    }

    public function isOpen(): bool
    {
        return $this->closedAt === null;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function target(): Money
    {
        return Money::fromMinorUnits((int) $this->target);
    }

    public function wantedBy(): ?DateTimeImmutable
    {
        return $this->wantedBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function reachedAt(): ?DateTimeImmutable
    {
        return $this->reachedAt;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    private static function assertName(string $name): void
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('A goal needs a name');
        }
    }
}
