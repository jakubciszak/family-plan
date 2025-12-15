<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use DomainException;

final readonly class CompletedExecutionState implements ExecutionStateInterface
{
    public function complete(TaskExecution $execution, Uuid $userId): void
    {
        throw new DomainException('Task execution is already completed');
    }

    public function approve(TaskExecution $execution, Uuid $adminId): void
    {
        $execution->transitionToApproved($adminId);
    }

    public function reject(TaskExecution $execution): void
    {
        $execution->transitionToRejected();
    }
}
