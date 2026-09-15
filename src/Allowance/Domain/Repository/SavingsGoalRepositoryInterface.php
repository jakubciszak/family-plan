<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Repository;

use App\Allowance\Domain\Entity\SavingsGoal;
use App\Shared\Domain\ValueObject\Uuid;

interface SavingsGoalRepositoryInterface
{
    public function find(Uuid $id): ?SavingsGoal;

    /**
     * @return SavingsGoal[]
     */
    public function ofUser(Uuid $userId, bool $openOnly = false): array;

    public function save(SavingsGoal $goal): void;
}
