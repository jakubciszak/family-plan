<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Event;

use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class MemberRemoved
{
    public function __construct(
        public readonly Uuid $teamId,
        public readonly Uuid $userId,
        public readonly DateTimeImmutable $occurredAt
    ) {
    }
}
