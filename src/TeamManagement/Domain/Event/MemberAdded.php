<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Event;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use DateTimeImmutable;

final class MemberAdded
{
    public function __construct(
        public readonly Uuid $teamId,
        public readonly Uuid $userId,
        public readonly TeamRole $role,
        public readonly DateTimeImmutable $occurredAt
    ) {
    }
}
