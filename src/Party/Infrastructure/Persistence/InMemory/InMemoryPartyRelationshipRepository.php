<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\InMemory;

use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Repository\PartyRelationshipRepositoryInterface;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * In-Memory Party Relationship Repository
 *
 * Implementation for testing purposes
 */
class InMemoryPartyRelationshipRepository implements PartyRelationshipRepositoryInterface
{
    /**
     * @var array<string, PartyRelationship>
     */
    private array $relationships = [];

    public function save(PartyRelationship $relationship): void
    {
        $this->relationships[$relationship->id()->value()] = $relationship;
    }

    public function findById(Uuid $id): ?PartyRelationship
    {
        return $this->relationships[$id->value()] ?? null;
    }

    public function findByFromParty(Uuid $fromPartyId): array
    {
        return array_values(
            array_filter(
                $this->relationships,
                fn(PartyRelationship $rel) => $rel->isFrom($fromPartyId)
            )
        );
    }

    public function findByToParty(Uuid $toPartyId): array
    {
        return array_values(
            array_filter(
                $this->relationships,
                fn(PartyRelationship $rel) => $rel->isTo($toPartyId)
            )
        );
    }

    public function findActiveRelationships(Uuid $fromPartyId, Uuid $toPartyId): array
    {
        return array_values(
            array_filter(
                $this->relationships,
                fn(PartyRelationship $rel) =>
                    $rel->isFrom($fromPartyId) &&
                    $rel->isTo($toPartyId) &&
                    $rel->isActive()
            )
        );
    }

    public function findByFromPartyAndType(Uuid $fromPartyId, PartyRelationshipType $type): array
    {
        return array_values(
            array_filter(
                $this->relationships,
                fn(PartyRelationship $rel) =>
                    $rel->isFrom($fromPartyId) &&
                    $rel->type()->equals($type)
            )
        );
    }

    public function isPartyAdminOf(Uuid $fromPartyId, Uuid $toPartyId): bool
    {
        foreach ($this->relationships as $relationship) {
            if ($relationship->isFrom($fromPartyId) &&
                $relationship->isTo($toPartyId) &&
                $relationship->type()->isAdminOf() &&
                $relationship->isActive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear all relationships (for testing)
     */
    public function clear(): void
    {
        $this->relationships = [];
    }
}
