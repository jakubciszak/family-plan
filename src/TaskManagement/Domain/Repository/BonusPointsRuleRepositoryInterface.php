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

    /**
     * Find all rules for a specific team
     * @return BonusPointsRule[]
     */
    public function findByTeamId(Uuid $teamId): array;

    /**
     * Find all active rules for a specific team
     * @return BonusPointsRule[]
     */
    public function findActiveByTeamId(Uuid $teamId): array;

    public function delete(BonusPointsRule $rule): void;
}
