<?php

declare(strict_types=1);

namespace App\Party\Domain\Entity;

use App\Party\Domain\Event\OrganizationCreated;
use App\Party\Domain\ValueObject\PartyType;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Organization Entity
 *
 * Represents a group, organization, or collective entity in the Party archetype.
 * Concrete implementation of Party.
 *
 * Examples: Teams, families, companies, groups.
 *
 * @see https://www.softwarearchetypes.com/archetypes/party/
 */
#[ORM\Entity]
class Organization extends Party
{
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    /**
     * Private constructor - use static factory method create()
     */
    private function __construct(
        Uuid $id,
        PartyType $type,
        DateTimeImmutable $createdAt,
        string $name,
        ?string $description
    ) {
        parent::__construct($id, $type, $createdAt);
        $this->name = $name;
        $this->description = $description;
    }

    /**
     * Factory method to create a new Organization
     */
    public static function create(Uuid $id, string $name, ?string $description): self
    {
        $organization = new self(
            $id,
            PartyType::organization(),
            new DateTimeImmutable(),
            $name,
            $description
        );

        $organization->record(new OrganizationCreated($id, $name, $description));

        return $organization;
    }

    /**
     * Get organization's name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get organization's description
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * Implementation of Party::getName()
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Update organization's name
     */
    public function updateName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Update organization's description
     */
    public function updateDescription(?string $description): void
    {
        $this->description = $description;
    }
}
