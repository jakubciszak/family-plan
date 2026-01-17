<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\InMemory;

use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\Party;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\Repository\PartyRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * In-Memory Party Repository
 *
 * Implementation for testing purposes
 */
class InMemoryPartyRepository implements PartyRepositoryInterface
{
    /**
     * @var array<string, Party>
     */
    private array $parties = [];

    public function save(Party $party): void
    {
        $this->parties[$party->id()->value()] = $party;
    }

    public function findById(Uuid $id): ?Party
    {
        return $this->parties[$id->value()] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->parties);
    }

    public function findPersons(): array
    {
        return array_values(
            array_filter(
                $this->parties,
                fn(Party $party) => $party instanceof Person
            )
        );
    }

    public function findOrganizations(): array
    {
        return array_values(
            array_filter(
                $this->parties,
                fn(Party $party) => $party instanceof Organization
            )
        );
    }

    /**
     * Clear all parties (for testing)
     */
    public function clear(): void
    {
        $this->parties = [];
    }
}
