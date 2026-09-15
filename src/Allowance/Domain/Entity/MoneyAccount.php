<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Entity;

use App\Allowance\Domain\ValueObject\AccountKind;
use App\Allowance\Domain\ValueObject\Money;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'allowance_accounts')]
#[ORM\UniqueConstraint(name: 'uniq_allowance_account', columns: ['user_id', 'kind', 'reference'])]
#[ORM\Index(columns: ['user_id'])]
class MoneyAccount
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $userId,

        #[ORM\Column(type: 'string', length: 30, enumType: AccountKind::class)]
        private AccountKind $kind,

        #[ORM\Column(type: 'string', length: 36)]
        private string $reference,

        #[ORM\Column(type: 'bigint')]
        private int $balance,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $updatedAt = null
    ) {
    }

    public static function open(Uuid $id, Uuid $userId, AccountKind $kind, ClockInterface $clock, ?Uuid $reference = null): self
    {
        if ($kind->needsReference() && $reference === null) {
            throw new \DomainException(sprintf('An account of kind %s is always kept for something', $kind->value));
        }

        return new self($id, $userId, $kind, $reference?->value() ?? '', 0, $clock->now());
    }

    public function post(MoneyEntry $entry, ClockInterface $clock): void
    {
        if (!$entry->belongsTo($this->id)) {
            throw new \DomainException('Entry belongs to another account');
        }

        $after = $this->balance + $entry->amount()->minorUnits();

        if ($after < 0 && $this->kind->mustStayPositive()) {
            throw new \DomainException(sprintf('There is not enough money on the %s account', $this->kind->value));
        }

        $this->balance = $after;
        $this->updatedAt = $clock->now();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function kind(): AccountKind
    {
        return $this->kind;
    }

    public function reference(): ?Uuid
    {
        return $this->reference === '' ? null : Uuid::fromString($this->reference);
    }

    public function balance(): Money
    {
        return Money::fromMinorUnits((int) $this->balance);
    }

    public function holds(Money $amount): bool
    {
        return !$amount->isGreaterThan($this->balance());
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
