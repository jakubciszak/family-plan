<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\StatusChangeRule;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\StatusChangeConditionType;
use DateTimeImmutable;

/**
 * Service to evaluate if status change rules are met
 * Implements rule evaluation logic for different condition types
 */
class StatusChangeRuleEvaluator
{
    public function __construct(
        private readonly TaskExecutionRepositoryInterface $executionRepository
    ) {
    }

    /**
     * Check if a rule allows the status change (assignment)
     * Returns true if the rule allows the change, false otherwise
     */
    public function isRuleMet(StatusChangeRule $rule, Uuid $userId): bool
    {
        if (!$rule->isActive()) {
            return true; // Inactive rules don't block anything
        }

        return match ($rule->conditionType()) {
            StatusChangeConditionType::OTHER_TASK_COMPLETED_TODAY => 
                $this->evaluateOtherTaskCompletedToday($rule, $userId),
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN => 
                $this->evaluateLastExecutionCooldown($rule, $userId),
        };
    }

    /**
     * Get a human-readable error message for a failed rule
     */
    public function getViolationMessage(StatusChangeRule $rule): string
    {
        $config = $rule->config();
        
        return match ($rule->conditionType()) {
            StatusChangeConditionType::OTHER_TASK_COMPLETED_TODAY => 
                sprintf(
                    'Cannot assign this task: Required task must be completed today first. %s',
                    $rule->description()
                ),
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN => 
                sprintf(
                    'Cannot assign this task: Last execution was less than %d days ago. %s',
                    $config->cooldownDays(),
                    $rule->description()
                ),
        };
    }

    private function evaluateOtherTaskCompletedToday(StatusChangeRule $rule, Uuid $userId): bool
    {
        $config = $rule->config();
        $requiredTaskTemplateId = $config->requiredTaskTemplateId();

        if ($requiredTaskTemplateId === null) {
            return false;
        }

        $today = new DateTimeImmutable('today');
        $executions = $this->executionRepository->findApprovedByUserTemplateAndDate(
            $userId,
            $requiredTaskTemplateId,
            $today
        );

        return count($executions) > 0;
    }

    private function evaluateLastExecutionCooldown(StatusChangeRule $rule, Uuid $userId): bool
    {
        $config = $rule->config();
        $cooldownDays = $config->cooldownDays();

        if ($cooldownDays === null) {
            return false;
        }

        $taskTemplateId = $rule->taskTemplateId();
        
        // Find the most recent approved execution for this task template and user
        $recentExecutions = $this->executionRepository->findRecentApprovedByUserAndTemplate(
            $userId,
            $taskTemplateId,
            1 // Get only the most recent one
        );

        if (empty($recentExecutions)) {
            return true; // No previous executions, cooldown doesn't apply
        }

        $lastExecution = $recentExecutions[0];
        $lastScheduledDate = $lastExecution->scheduledFor();
        $now = new DateTimeImmutable();
        
        $daysSinceLastExecution = $now->diff($lastScheduledDate)->days;
        
        return $daysSinceLastExecution >= $cooldownDays;
    }
}
