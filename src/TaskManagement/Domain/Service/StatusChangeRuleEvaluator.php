<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\StatusChangeRule;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\StatusChangeConditionType;
use DateTimeImmutable;
use JakubCiszak\RuleEngine\Api\NestedRuleApi;

/**
 * Service to evaluate if status change rules are met using rule-engine library
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

        // Build context data for rule evaluation
        $context = $this->buildRuleContext($rule, $userId);
        
        // Build rule definition based on condition type
        $ruleDefinition = $this->buildRuleDefinition($rule);
        
        // Evaluate using rule-engine
        return NestedRuleApi::evaluate($ruleDefinition, $context);
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

    /**
     * Build context data for rule evaluation
     */
    private function buildRuleContext(StatusChangeRule $rule, Uuid $userId): array
    {
        $config = $rule->config();
        
        return match ($rule->conditionType()) {
            StatusChangeConditionType::OTHER_TASK_COMPLETED_TODAY => [
                'hasCompletedRequiredTask' => $this->hasCompletedRequiredTaskToday(
                    $userId,
                    $config->requiredTaskTemplateId()
                ),
            ],
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN => [
                'daysSinceLastExecution' => $this->getDaysSinceLastExecution(
                    $userId,
                    $rule->taskTemplateId()
                ),
                'requiredCooldownDays' => $config->cooldownDays() ?? 0,
            ],
        };
    }

    /**
     * Build rule definition for rule-engine evaluation
     */
    private function buildRuleDefinition(StatusChangeRule $rule): array
    {
        return match ($rule->conditionType()) {
            StatusChangeConditionType::OTHER_TASK_COMPLETED_TODAY => [
                '==' => [
                    ['var' => 'hasCompletedRequiredTask'],
                    true
                ]
            ],
            StatusChangeConditionType::LAST_EXECUTION_COOLDOWN => [
                '>=' => [
                    ['var' => 'daysSinceLastExecution'],
                    ['var' => 'requiredCooldownDays']
                ]
            ],
        };
    }

    /**
     * Check if user has completed required task today
     */
    private function hasCompletedRequiredTaskToday(?Uuid $userId, ?Uuid $requiredTaskTemplateId): bool
    {
        if ($userId === null || $requiredTaskTemplateId === null) {
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

    /**
     * Get number of days since last execution
     */
    private function getDaysSinceLastExecution(Uuid $userId, Uuid $taskTemplateId): int
    {
        $recentExecutions = $this->executionRepository->findRecentApprovedByUserAndTemplate(
            $userId,
            $taskTemplateId,
            1 // Get only the most recent one
        );

        if (empty($recentExecutions)) {
            return PHP_INT_MAX; // No previous executions, cooldown doesn't apply
        }

        $lastExecution = $recentExecutions[0];
        $lastScheduledDate = $lastExecution->scheduledFor();
        $now = new DateTimeImmutable();
        
        return (int) $now->diff($lastScheduledDate)->days;
    }
}
