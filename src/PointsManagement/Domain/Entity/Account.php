<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\Entity;

use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\PointsManagement\Domain\ValueObject\PointsBalance;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'points_accounts')]
#[ORM\UniqueConstraint(name: 'uniq_account_user_kind', columns: ['user_id', 'kind'])]
#[ORM\Index(columns: ['user_id'])]
class Account
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $userId,

        #[ORM\Column(type: 'string', length: 30, enumType: AccountKind::class)]
        private AccountKind $kind,

        #[ORM\Column(type: 'integer')]
        private int $balance,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $updatedAt = null
    ) {
    }

    public static function open(Uuid $id, Uuid $userId, AccountKind $kind, ClockInterface $clock): self
    {
        return new self($id, $userId, $kind, 0, $clock->now());
    }

    public function post(Entry $entry, ClockInterface $clock): void
    {
        if (!$entry->belongsTo($this->id)) {
            throw new \DomainException('Entry belongs to another account');
        }

        $this->balance += $entry->amount();
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

    public function balance(): PointsBalance
    {
        return PointsBalance::fromInt($this->balance);
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
