<?php

declare(strict_types=1);

namespace App\Party\Domain\Repository;

use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;

interface PartyRelationshipRepositoryInterface
{
    public function save(PartyRelationship $relationship): void;

    public function findById(Uuid $id): ?PartyRelationship;

    /**
     * @return PartyRelationship[]
     */
    public function findByFromRole(Uuid $fromPartyRoleId): array;

    /**
     * @return PartyRelationship[]
     */
    public function findByToRole(Uuid $toPartyRoleId): array;

    /**
     * @return PartyRelationship[]
     */
    public function findByFromParty(Uuid $fromPartyId): array;

    /**
     * @return PartyRelationship[]
     */
    public function findByToParty(Uuid $toPartyId): array;

    /**
     * @return PartyRelationship[]
     */
    public function findActiveRelationships(Uuid $fromPartyId, Uuid $toPartyId): array;

    /**
     * @return PartyRelationship[]
     */
    public function findByFromPartyAndType(Uuid $fromPartyId, PartyRelationshipType $type): array;

    public function isPartyAdminOf(Uuid $fromPartyId, Uuid $toPartyId): bool;
}
