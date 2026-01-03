<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Repository\StatusChangeRuleRepositoryInterface;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\Exception\StatusChangeRuleViolationException;

/**
 * Service to validate task assignments against status change rules
 */
class TaskAssignmentValidator
{
    public function __construct(
        private readonly StatusChangeRuleRepositoryInterface $ruleRepository,
        private readonly StatusChangeRuleEvaluator $ruleEvaluator
    ) {
    }

    /**
     * Validate if a TaskTemplate can be assigned to a user
     * @throws StatusChangeRuleViolationException
     */
    public function validateTaskTemplateAssignment(TaskTemplate $taskTemplate, Uuid $userId): void
    {
        $rules = $this->ruleRepository->findActiveByTaskTemplateId($taskTemplate->id());

        foreach ($rules as $rule) {
            if (!$this->ruleEvaluator->isRuleMet($rule, $userId)) {
                throw new StatusChangeRuleViolationException(
                    $this->ruleEvaluator->getViolationMessage($rule)
                );
            }
        }
    }

    /**
     * Validate if a Task can be assigned to a user
     * @throws StatusChangeRuleViolationException
     */
    public function validateTaskAssignment(Task $task, Uuid $userId): void
    {
        // Tasks might be based on templates, but since we don't have a direct link,
        // we can skip this for now or add template tracking to Task entity later
        // For now, this is a placeholder
    }

    /**
     * Validate if a TaskExecution can be assigned to a user
     * @throws StatusChangeRuleViolationException
     */
    public function validateTaskExecutionAssignment(TaskExecution $execution, Uuid $userId): void
    {
        $taskTemplateId = $execution->taskTemplateId();
        
        if ($taskTemplateId === null) {
            // One-time execution, no template, no rules apply
            return;
        }

        $rules = $this->ruleRepository->findActiveByTaskTemplateId($taskTemplateId);

        foreach ($rules as $rule) {
            if (!$this->ruleEvaluator->isRuleMet($rule, $userId)) {
                throw new StatusChangeRuleViolationException(
                    $this->ruleEvaluator->getViolationMessage($rule)
                );
            }
        }
    }
}
