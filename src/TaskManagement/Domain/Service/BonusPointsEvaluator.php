<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\RuleType;

/**
 * Service to evaluate if bonus point rules are met
 * Implements the Strategy pattern for different rule types
 */
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

        // Check if we have the required consecutive days
        return $this->hasConsecutiveDays($executions, $requiredDays);
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

    /**
     * Check if executions contain consecutive days
     * @param array $executions
     * @param int $requiredDays
     * @return bool
     */
    private function hasConsecutiveDays(array $executions, int $requiredDays): bool
    {
        if (empty($executions)) {
            return false;
        }

        // Sort executions by scheduled date (newest first)
        usort($executions, function ($a, $b) {
            return $b->scheduledFor() <=> $a->scheduledFor();
        });

        $consecutiveCount = 1;
        $previousDate = $executions[0]->scheduledFor();

        for ($i = 1; $i < count($executions); $i++) {
            $currentDate = $executions[$i]->scheduledFor();
            
            // Calculate difference in days
            $diff = $previousDate->diff($currentDate);
            
            if ($diff->days === 1 && $diff->invert === 1) {
                // Dates are consecutive (previous day is 1 day after current)
                $consecutiveCount++;
                
                if ($consecutiveCount >= $requiredDays) {
                    return true;
                }
            } else {
                // Reset counter if days are not consecutive
                $consecutiveCount = 1;
            }
            
            $previousDate = $currentDate;
        }

        return $consecutiveCount >= $requiredDays;
    }
}
