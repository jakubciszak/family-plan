<?php

declare(strict_types=1);

namespace App\Party\Domain\Event;

use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class PartyRelationshipCreated
{
    public function __construct(
        public Uuid $relationshipId,
        public Uuid $fromPartyRoleId,
        public Uuid $toPartyRoleId,
        public PartyRelationshipType $type
    ) {
    }
}
