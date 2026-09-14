<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\Entity;

use App\PointsManagement\Domain\ValueObject\EntrySource;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'points_entries')]
#[ORM\UniqueConstraint(name: 'uniq_entry_account_reference_period', columns: ['account_id', 'reference', 'period_key'])]
#[ORM\Index(columns: ['account_id', 'booked_at'])]
class Entry
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $accountId,

        #[ORM\Column(type: 'integer')]
        private int $amount,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $bookedAt,

        #[ORM\Column(type: 'string', length: 30, enumType: EntrySource::class)]
        private EntrySource $source,

        #[ORM\Column(type: 'string', length: 36, nullable: true)]
        private ?string $reference,

        #[ORM\Column(type: 'string', length: 20, nullable: true)]
        private ?string $periodKey,

        #[ORM\Column(type: 'string', length: 255)]
        private string $description
    ) {
    }

    public static function record(
        Uuid $id,
        Uuid $accountId,
        int $amount,
        EntrySource $source,
        string $description,
        DateTimeImmutable $bookedAt,
        ?Uuid $reference = null,
        ?string $periodKey = null
    ): self {
        if ($amount === 0) {
            throw new \DomainException('An entry cannot be for zero points');
        }

        return new self(
            $id,
            $accountId,
            $amount,
            $bookedAt,
            $source,
            $reference?->value(),
            $periodKey,
            $description
        );
    }

    public function belongsTo(Uuid $accountId): bool
    {
        return $this->accountId->equals($accountId);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function accountId(): Uuid
    {
        return $this->accountId;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function bookedAt(): DateTimeImmutable
    {
        return $this->bookedAt;
    }

    public function source(): EntrySource
    {
        return $this->source;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    public function periodKey(): ?string
    {
        return $this->periodKey;
    }

    public function description(): string
    {
        return $this->description;
    }
}
