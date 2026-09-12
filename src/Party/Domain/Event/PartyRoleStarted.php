<?php

declare(strict_types=1);

namespace App\Party\Domain\Event;

use App\Party\Domain\ValueObject\PartyRoleType;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final readonly class PartyRoleStarted
{
    public function __construct(
        public Uuid $roleId,
        public Uuid $partyId,
        public PartyRoleType $type,
        public DateTimeImmutable $startedAt
    ) {
    }
}
