<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;

interface TaskStateInterface
{
    public function complete(Task $task, Uuid $userId): void;
    
    public function approve(Task $task, Uuid $adminId): void;
    
    public function reject(Task $task): void;
    
    public function canTransitionTo(string $newState): bool;
    
    public function getStateName(): string;
}
