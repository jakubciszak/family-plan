<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\ValueObject\TaskStatus;

final class NewState implements TaskStateInterface
{
    public function complete(Task $task, Uuid $userId): void
    {
        // Transition to completed state
        $task->transitionToState(new CompletedState(), $userId);
    }
    
    public function approve(Task $task, Uuid $adminId): void
    {
        throw new \DomainException('Only completed tasks can be approved');
    }
    
    public function reject(Task $task): void
    {
        throw new \DomainException('Only completed tasks can be rejected');
    }
    
    public function canTransitionTo(string $newState): bool
    {
        return $newState === TaskStatus::COMPLETED->value;
    }
    
    public function getStateName(): string
    {
        return TaskStatus::NEW->value;
    }
}
