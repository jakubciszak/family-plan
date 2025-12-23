<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\ValueObject\ExecutionStatus;

final class NewExecutionState implements ExecutionStateInterface
{
    public function complete(TaskExecution $execution, Uuid $userId): void
    {
        // Transition to completed state
        $execution->transitionToState(new CompletedExecutionState(), $userId);
    }
    
    public function approve(TaskExecution $execution, Uuid $adminId): void
    {
        throw new \DomainException('Only completed task executions can be approved');
    }
    
    public function reject(TaskExecution $execution): void
    {
        throw new \DomainException('Only completed task executions can be rejected');
    }
    
    public function canTransitionTo(string $newState): bool
    {
        return $newState === ExecutionStatus::COMPLETED->value;
    }
    
    public function getStateName(): string
    {
        return ExecutionStatus::NEW->value;
    }
}
