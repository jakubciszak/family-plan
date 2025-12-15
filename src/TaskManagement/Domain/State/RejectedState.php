<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\ValueObject\TaskStatus;

final class RejectedState implements TaskStateInterface
{
    public function complete(Task $task, Uuid $userId): void
    {
        throw new \DomainException('Cannot complete a rejected task. Create a new task instead.');
    }
    
    public function approve(Task $task, Uuid $adminId): void
    {
        throw new \DomainException('Cannot approve a rejected task.');
    }
    
    public function reject(Task $task): void
    {
        throw new \DomainException('Task is already rejected.');
    }
    
    public function canTransitionTo(string $newState): bool
    {
        // Rejected is a terminal state
        return false;
    }
    
    public function getStateName(): string
    {
        return TaskStatus::REJECTED->value;
    }
}
