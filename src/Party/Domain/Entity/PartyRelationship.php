<?php

declare(strict_types=1);

namespace App\Party\Domain\Entity;

use App\Party\Domain\Event\PartyRelationshipCreated;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use DomainException;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'party_relationships')]
#[ORM\Index(columns: ['from_party_role_id'])]
#[ORM\Index(columns: ['to_party_role_id'])]
#[ORM\Index(columns: ['type'])]
class PartyRelationship
{
    #[ORM\Transient]
    private array $domainEvents = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\ManyToOne(targetEntity: PartyRole::class)]
        #[ORM\JoinColumn(name: 'from_party_role_id', nullable: false)]
        private PartyRole $from,

        #[ORM\ManyToOne(targetEntity: PartyRole::class)]
        #[ORM\JoinColumn(name: 'to_party_role_id', nullable: false)]
        private PartyRole $to,

        #[ORM\Column(type: 'party_relationship_type')]
        private PartyRelationshipType $type,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $endedAt = null
    ) {
    }

    public static function create(
        Uuid $id,
        PartyRole $from,
        PartyRole $to,
        PartyRelationshipType $type
    ): self {
        if (!$type->connects($from->type(), $to->type())) {
            throw new DomainException(sprintf(
                '%s connects %s to %s, not %s to %s',
                $type->value(),
                $type->fromRoleType()->value(),
                $type->toRoleType()->value(),
                $from->type()->value(),
                $to->type()->value()
            ));
        }

        $relationship = new self($id, $from, $to, $type, new DateTimeImmutable());

        $relationship->record(new PartyRelationshipCreated($id, $from->id(), $to->id(), $type));

        return $relationship;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function from(): PartyRole
    {
        return $this->from;
    }

    public function to(): PartyRole
    {
        return $this->to;
    }

    public function fromParty(): Party
    {
        return $this->from->party();
    }

    public function toParty(): Party
    {
        return $this->to->party();
    }

    public function type(): PartyRelationshipType
    {
        return $this->type;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function isActive(): bool
    {
        return $this->endedAt === null;
    }

    public function end(DateTimeImmutable $endDate): void
    {
        $this->endedAt = $endDate;
    }

    public function isFrom(Uuid $partyRoleId): bool
    {
        return $this->from->id()->equals($partyRoleId);
    }

    public function isTo(Uuid $partyRoleId): bool
    {
        return $this->to->id()->equals($partyRoleId);
    }

    public function isPlayedFrom(Uuid $partyId): bool
    {
        return $this->from->partyId()->equals($partyId);
    }

    public function isPlayedTo(Uuid $partyId): bool
    {
        return $this->to->partyId()->equals($partyId);
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
