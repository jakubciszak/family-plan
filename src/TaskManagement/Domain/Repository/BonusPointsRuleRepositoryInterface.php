<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\BonusPointsRule;

interface BonusPointsRuleRepositoryInterface
{
    public function save(BonusPointsRule $rule): void;

    public function findById(Uuid $id): ?BonusPointsRule;

    /**
     * Find all bonus points rules
     * @return BonusPointsRule[]
     */
    public function findAll(): array;

    /**
     * Find all active bonus points rules
     * @return BonusPointsRule[]
     */
    public function findActive(): array;

    public function delete(BonusPointsRule $rule): void;
}
