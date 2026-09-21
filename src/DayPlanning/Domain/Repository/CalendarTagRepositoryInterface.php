<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\Repository;

use App\DayPlanning\Domain\Entity\CalendarTag;
use App\Shared\Domain\ValueObject\Uuid;

interface CalendarTagRepositoryInterface
{
    public function find(Uuid $id): ?CalendarTag;
    public function visibleTo(Uuid $ownerId, ?Uuid $teamId): array;
    public function save(CalendarTag $tag): void;
}
