<?php

declare(strict_types=1);

namespace App\Allowance\Application\Service;

use App\Allowance\Domain\Entity\SavingsGoal;
use App\Allowance\Domain\Repository\SavingsGoalRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;

final class Goals
{
    public static function open(SavingsGoalRepositoryInterface $goals, string $goalId): SavingsGoal
    {
        $goal = $goals->find(Uuid::fromString($goalId));

        if ($goal === null || !$goal->isOpen()) {
            throw new \DomainException('No such goal is being saved for');
        }

        return $goal;
    }
}
