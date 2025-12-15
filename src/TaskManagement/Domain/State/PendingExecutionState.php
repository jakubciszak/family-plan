<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use DomainException;

final readonly class PendingExecutionState implements ExecutionStateInterface
{
    public function complete(TaskExecution $execution, Uuid $userId): void
    {
        $execution->transitionToState(new CompletedExecutionState(), $userId);
    }

    public function approve(TaskExecution $execution, Uuid $adminId): void
    {
        throw new DomainException('Only completed task executions can be approved');
    }

    public function reject(TaskExecution $execution): void
    {
        throw new DomainException('Only completed task executions can be rejected');
    }
}
