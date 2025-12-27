<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\RuleType;
use JakubCiszak\RuleEngine\Api\NestedRuleApi;

/**
 * Service to evaluate if bonus point rules are met
 * Uses jakubciszak/rule-engine library for flexible rule evaluation
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

        // Prepare context data based on rule type
        $context = $this->prepareContext($rule, $userId);

        // Use jakubciszak/rule-engine to evaluate the rule
        return NestedRuleApi::evaluate($rule->config()->ruleDefinition(), $context);
    }

    private function prepareContext(BonusPointsRule $rule, Uuid $userId): array
    {
        return match ($rule->type()) {
            RuleType::CONSECUTIVE_DAYS => $this->prepareConsecutiveDaysContext($rule, $userId),
            RuleType::MONTHLY_TASK_COUNT => $this->prepareMonthlyTaskCountContext($userId),
        };
    }

    private function prepareConsecutiveDaysContext(BonusPointsRule $rule, Uuid $userId): array
    {
        $config = $rule->config();
        $taskTemplateId = $config->taskTemplateId();
        $requiredDays = $config->requiredDays();

        if ($taskTemplateId === null || $requiredDays === null) {
            return [
                'taskTemplateId' => null,
                'consecutiveDays' => 0,
            ];
        }

        $executions = $this->executionRepository->findRecentApprovedByUserAndTemplate(
            $userId,
            $taskTemplateId,
            $requiredDays * 2 // Get more than needed to check for consecutive days
        );

        $consecutiveDays = $this->countConsecutiveDays($executions);

        return [
            'taskTemplateId' => $taskTemplateId->value(),
            'consecutiveDays' => $consecutiveDays,
        ];
    }

    private function prepareMonthlyTaskCountContext(Uuid $userId): array
    {
        $completedCount = $this->executionRepository->countApprovedInCurrentMonth($userId);

        return [
            'monthlyTaskCount' => $completedCount,
        ];
    }

    /**
     * Count the maximum number of consecutive days in executions
     * @param array $executions
     * @return int
     */
    private function countConsecutiveDays(array $executions): int
    {
        if (empty($executions)) {
            return 0;
        }

        // Sort executions by scheduled date (newest first)
        usort($executions, function ($a, $b) {
            return $b->scheduledFor() <=> $a->scheduledFor();
        });

        $maxConsecutive = 1;
        $currentConsecutive = 1;
        $previousDate = $executions[0]->scheduledFor();

        for ($i = 1; $i < count($executions); $i++) {
            $currentDate = $executions[$i]->scheduledFor();
            
            // Calculate difference in days
            $diff = $previousDate->diff($currentDate);
            
            if ($diff->days === 1 && $diff->invert === 1) {
                // Dates are consecutive (previous day is 1 day after current)
                $currentConsecutive++;
                $maxConsecutive = max($maxConsecutive, $currentConsecutive);
            } else {
                // Reset counter if days are not consecutive
                $currentConsecutive = 1;
            }
            
            $previousDate = $currentDate;
        }

        return $maxConsecutive;
    }
}
