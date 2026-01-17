<?php

declare(strict_types=1);

namespace App\Party\Domain\Repository;

use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Party Relationship Repository Interface
 *
 * Repository for managing PartyRelationship entities
 */
interface PartyRelationshipRepositoryInterface
{
    /**
     * Save a party relationship to the repository
     */
    public function save(PartyRelationship $relationship): void;

    /**
     * Find a party relationship by its ID
     */
    public function findById(Uuid $id): ?PartyRelationship;

    /**
     * Find all relationships where the given party is the "from" party
     *
     * @return PartyRelationship[]
     */
    public function findByFromParty(Uuid $fromPartyId): array;

    /**
     * Find all relationships where the given party is the "to" party
     *
     * @return PartyRelationship[]
     */
    public function findByToParty(Uuid $toPartyId): array;

    /**
     * Find active relationships between two parties
     *
     * @return PartyRelationship[]
     */
    public function findActiveRelationships(Uuid $fromPartyId, Uuid $toPartyId): array;

    /**
     * Find relationships by from party and type
     *
     * @return PartyRelationship[]
     */
    public function findByFromPartyAndType(Uuid $fromPartyId, PartyRelationshipType $type): array;

    /**
     * Check if a party is an admin of another party
     */
    public function isPartyAdminOf(Uuid $fromPartyId, Uuid $toPartyId): bool;
}
