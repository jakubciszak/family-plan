<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Event;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ValueObject\TeamName;
use DateTimeImmutable;

final class TeamCreated
{
    public function __construct(
        public readonly Uuid $teamId,
        public readonly TeamName $teamName,
        public readonly Uuid $createdBy,
        public readonly DateTimeImmutable $occurredAt
    ) {
    }
}
