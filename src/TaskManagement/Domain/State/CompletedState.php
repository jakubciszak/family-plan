<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\ValueObject\TaskStatus;

final class CompletedState implements TaskStateInterface
{
    public function complete(Task $task, Uuid $userId): void
    {
        throw new \DomainException('Task is already completed.');
    }
    
    public function approve(Task $task, Uuid $adminId): void
    {
        // Transition to approved state
        $task->transitionToApproved($adminId);
    }
    
    public function reject(Task $task): void
    {
        // Transition to rejected state
        $task->transitionToRejected();
    }
    
    public function canTransitionTo(string $newState): bool
    {
        return in_array($newState, [
            TaskStatus::APPROVED->value,
            TaskStatus::REJECTED->value
        ]);
    }
    
    public function getStateName(): string
    {
        return TaskStatus::COMPLETED->value;
    }
}
