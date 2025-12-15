<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\TaskManagement\Domain\ValueObject\ExecutionStatus;
use DomainException;

final class ExecutionStateFactory
{
    public static function createFromStatus(ExecutionStatus $status): ExecutionStateInterface
    {
        return match($status) {
            ExecutionStatus::PENDING => new PendingExecutionState(),
            ExecutionStatus::COMPLETED => new CompletedExecutionState(),
            ExecutionStatus::APPROVED => new ApprovedExecutionState(),
            ExecutionStatus::REJECTED => new RejectedExecutionState(),
            default => throw new DomainException('Unknown execution status: ' . $status->value)
        };
    }
}
