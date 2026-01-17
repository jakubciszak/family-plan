<?php

declare(strict_types=1);

namespace App\Party\Domain\Entity;

use App\Party\Domain\ValueObject\PartyType;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Party - Abstract Entity
 *
 * Base class for all parties (Person, Organization).
 * Implements the Party archetype pattern from software archetypes.
 *
 * @see https://www.softwarearchetypes.com/archetypes/party/
 *
 * A Party represents any entity that can participate in relationships,
 * transactions, or other business activities. This can be:
 * - A Person (individual human being)
 * - An Organization (group of people)
 */
#[ORM\Entity]
#[ORM\Table(name: 'parties')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'party_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'PERSON' => 'App\Party\Domain\Entity\Person',
    'ORGANIZATION' => 'App\Party\Domain\Entity\Organization'
])]
abstract class Party
{
    #[ORM\Transient]
    protected array $domainEvents = [];

    protected function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        protected Uuid $id,

        #[ORM\Column(type: 'string', length: 50)]
        protected PartyType $type,

        #[ORM\Column(type: 'datetime_immutable')]
        protected DateTimeImmutable $createdAt
    ) {
    }

    /**
     * Get the party's display name
     * Must be implemented by concrete classes
     */
    abstract public function getName(): string;

    public function id(): Uuid
    {
        return $this->id;
    }

    public function type(): PartyType
    {
        return $this->type;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isPerson(): bool
    {
        return $this->type->isPerson();
    }

    public function isOrganization(): bool
    {
        return $this->type->isOrganization();
    }

    public function equals(self $other): bool
    {
        return $this->id->value() === $other->id->value();
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    protected function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
