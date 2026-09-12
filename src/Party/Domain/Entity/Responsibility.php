<?php

declare(strict_types=1);

namespace App\Party\Domain\Entity;

use App\Party\Domain\ValueObject\ResponsibilityType;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use DomainException;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'party_responsibilities')]
#[ORM\Index(columns: ['party_role_id'])]
#[ORM\Index(columns: ['type'])]
class Responsibility
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\ManyToOne(targetEntity: PartyRole::class)]
        #[ORM\JoinColumn(name: 'party_role_id', nullable: false)]
        private PartyRole $partyRole,

        #[ORM\Column(type: 'responsibility_type', length: 50)]
        private ResponsibilityType $type,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $startedAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $endedAt = null
    ) {
    }

    public static function allocate(Uuid $id, PartyRole $partyRole, ResponsibilityType $type): self
    {
        return new self($id, $partyRole, $type, new DateTimeImmutable());
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function partyRole(): PartyRole
    {
        return $this->partyRole;
    }

    public function type(): ResponsibilityType
    {
        return $this->type;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function isActive(): bool
    {
        return $this->endedAt === null && $this->partyRole->isActive();
    }

    public function covers(ResponsibilityType $type): bool
    {
        return $this->type->equals($type) && $this->isActive();
    }

    public function withdraw(): void
    {
        if ($this->endedAt !== null) {
            throw new DomainException('This responsibility has already been withdrawn');
        }

        $this->endedAt = new DateTimeImmutable();
    }
}
