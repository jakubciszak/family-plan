<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Domain\Repository;

use App\SchoolTimetable\Domain\Entity\SchoolAccount;
use App\Shared\Domain\ValueObject\Uuid;

interface SchoolAccountRepositoryInterface
{
    public function ofTeam(Uuid $teamId): ?SchoolAccount;

    /**
     * @return SchoolAccount[]
     */
    public function all(): array;

    public function save(SchoolAccount $account): void;

    public function remove(SchoolAccount $account): void;
}
