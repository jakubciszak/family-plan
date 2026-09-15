<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Repository;

use App\Allowance\Domain\Entity\AllowanceRule;
use App\PointsManagement\Domain\ValueObject\AccountKind as PointsAccountKind;
use App\Shared\Domain\ValueObject\Uuid;

interface AllowanceRuleRepositoryInterface
{
    public function find(Uuid $teamId, PointsAccountKind $pointsAccount): ?AllowanceRule;

    /**
     * @return AllowanceRule[]
     */
    public function ofTeam(Uuid $teamId): array;

    public function save(AllowanceRule $rule): void;

    public function remove(AllowanceRule $rule): void;
}
