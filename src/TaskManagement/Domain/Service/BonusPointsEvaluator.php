<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\RuleType;

class BonusPointsEvaluator
{
    public function __construct(
        private readonly TaskExecutionRepositoryInterface $executionRepository
    ) {
    }

    public function isRuleMet(BonusPointsRule $rule, Uuid $userId): bool
    {
        if (!$rule->isActive()) {
            return false;
        }

        return match ($rule->type()) {
            RuleType::CONSECUTIVE_DAYS => $this->evaluateConsecutiveDays($rule, $userId),
            RuleType::MONTHLY_TASK_COUNT => $this->evaluateMonthlyTaskCount($rule, $userId),
        };
    }

    private function evaluateConsecutiveDays(BonusPointsRule $rule, Uuid $userId): bool
    {
        $config = $rule->config();
        $taskTemplateId = $config->taskTemplateId();
        $requiredDays = $config->requiredDays();

        if ($taskTemplateId === null || $requiredDays === null) {
            return false;
        }

        $executions = $this->executionRepository->findRecentApprovedByUserAndTemplate(
            $userId,
            $taskTemplateId,
            $requiredDays * 2 // Get more than needed to check for consecutive days
        );

        if (count($executions) < $requiredDays) {
            return false;
        }

        return ExecutionStreak::longest($executions) >= $requiredDays;
    }

    private function evaluateMonthlyTaskCount(BonusPointsRule $rule, Uuid $userId): bool
    {
        $config = $rule->config();
        $requiredCount = $config->requiredCount();

        if ($requiredCount === null) {
            return false;
        }

        $completedCount = $this->executionRepository->countApprovedInCurrentMonth($userId);

        return $completedCount >= $requiredCount;
    }
}
