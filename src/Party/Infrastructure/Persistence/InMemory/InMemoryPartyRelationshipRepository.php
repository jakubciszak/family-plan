<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\InMemory;

use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Repository\PartyRelationshipRepositoryInterface;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;

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

    public function findByFromRole(Uuid $fromPartyRoleId): array
    {
        return $this->filter(fn (PartyRelationship $rel) => $rel->isFrom($fromPartyRoleId));
    }

    public function findByToRole(Uuid $toPartyRoleId): array
    {
        return $this->filter(fn (PartyRelationship $rel) => $rel->isTo($toPartyRoleId));
    }

    public function findByFromParty(Uuid $fromPartyId): array
    {
        return $this->filter(fn (PartyRelationship $rel) => $rel->isPlayedFrom($fromPartyId));
    }

    public function findByToParty(Uuid $toPartyId): array
    {
        return $this->filter(fn (PartyRelationship $rel) => $rel->isPlayedTo($toPartyId));
    }

    public function findActiveRelationships(Uuid $fromPartyId, Uuid $toPartyId): array
    {
        return $this->filter(
            fn (PartyRelationship $rel) => $rel->isPlayedFrom($fromPartyId)
                && $rel->isPlayedTo($toPartyId)
                && $rel->isActive()
        );
    }

    public function findByFromPartyAndType(Uuid $fromPartyId, PartyRelationshipType $type): array
    {
        return $this->filter(
            fn (PartyRelationship $rel) => $rel->isPlayedFrom($fromPartyId) && $rel->type()->equals($type)
        );
    }

    public function isPartyAdminOf(Uuid $fromPartyId, Uuid $toPartyId): bool
    {
        foreach ($this->relationships as $relationship) {
            if ($relationship->isPlayedFrom($fromPartyId)
                && $relationship->isPlayedTo($toPartyId)
                && $relationship->type()->isAdminOf()
                && $relationship->isActive()
            ) {
                return true;
            }
        }

        return false;
    }

    public function clear(): void
    {
        $this->relationships = [];
    }

    /**
     * @return PartyRelationship[]
     */
    private function filter(callable $matches): array
    {
        return array_values(array_filter($this->relationships, $matches));
    }
}
