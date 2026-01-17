<?php

declare(strict_types=1);

namespace App\Party\Domain\Event;

use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Domain Event: Party Relationship Created
 *
 * Emitted when a new relationship between two parties is created.
 */
final readonly class PartyRelationshipCreated
{
    public function __construct(
        public Uuid $relationshipId,
        public Uuid $fromPartyId,
        public Uuid $toPartyId,
        public PartyRelationshipType $type
    ) {
    }
}
