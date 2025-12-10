<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\ValueObject\TaskStatus;

final class ApprovedState implements TaskStateInterface
{
    public function complete(Task $task, Uuid $userId): void
    {
        throw new \DomainException('Cannot complete an already approved task.');
    }
    
    public function approve(Task $task, Uuid $adminId): void
    {
        throw new \DomainException('Task is already approved.');
    }
    
    public function reject(Task $task): void
    {
        throw new \DomainException('Cannot reject an approved task.');
    }
    
    public function canTransitionTo(string $newState): bool
    {
        // Approved is a terminal state
        return false;
    }
    
    public function getStateName(): string
    {
        return TaskStatus::APPROVED->value;
    }
}
