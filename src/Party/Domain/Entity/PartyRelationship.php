<?php

declare(strict_types=1);

namespace App\Party\Domain\Entity;

use App\Party\Domain\Event\PartyRelationshipCreated;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * PartyRelationship Entity
 *
 * Represents a relationship between two parties.
 * Based on Party archetype pattern.
 *
 * Examples:
 * - Person is MEMBER_OF Organization (e.g., Alice is member of Smith Family)
 * - Person is ADMIN_OF Organization (e.g., Bob is admin of Smith Family)
 *
 * @see https://www.softwarearchetypes.com/archetypes/party/
 */
#[ORM\Entity]
#[ORM\Table(name: 'party_relationships')]
class PartyRelationship
{
    #[ORM\Transient]
    private array $domainEvents = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\ManyToOne(targetEntity: Party::class)]
        #[ORM\JoinColumn(name: 'from_party_id', nullable: false)]
        private Party $from,

        #[ORM\ManyToOne(targetEntity: Party::class)]
        #[ORM\JoinColumn(name: 'to_party_id', nullable: false)]
        private Party $to,

        #[ORM\Column(type: 'party_relationship_type')]
        private PartyRelationshipType $type,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $endedAt = null
    ) {
    }

    /**
     * Factory method to create a new PartyRelationship
     */
    public static function create(
        Uuid $id,
        Party $from,
        Party $to,
        PartyRelationshipType $type
    ): self {
        $relationship = new self(
            $id,
            $from,
            $to,
            $type,
            new DateTimeImmutable(),
            null
        );

        $relationship->record(new PartyRelationshipCreated(
            $id,
            $from->id(),
            $to->id(),
            $type
        ));

        return $relationship;
    }

    /**
     * Get relationship ID
     */
    public function id(): Uuid
    {
        return $this->id;
    }

    /**
     * Get the party that the relationship is from (the source)
     */
    public function from(): Party
    {
        return $this->from;
    }

    /**
     * Get the party that the relationship is to (the target)
     */
    public function to(): Party
    {
        return $this->to;
    }

    /**
     * Get relationship type
     */
    public function type(): PartyRelationshipType
    {
        return $this->type;
    }

    /**
     * Get creation date
     */
    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get end date (null if active)
     */
    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    /**
     * Check if relationship is active
     */
    public function isActive(): bool
    {
        return $this->endedAt === null;
    }

    /**
     * End the relationship
     */
    public function end(DateTimeImmutable $endDate): void
    {
        $this->endedAt = $endDate;
    }

    /**
     * Check if the given party ID is the "from" party
     */
    public function isFrom(Uuid $partyId): bool
    {
        return $this->from->id()->equals($partyId);
    }

    /**
     * Check if the given party ID is the "to" party
     */
    public function isTo(Uuid $partyId): bool
    {
        return $this->to->id()->equals($partyId);
    }

    /**
     * Pull domain events (clears after pulling)
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    /**
     * Record a domain event
     */
    private function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
