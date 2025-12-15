<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\TaskManagement\Domain\ValueObject\TaskStatus;

final class TaskStateFactory
{
    public static function createFromStatus(TaskStatus $status): TaskStateInterface
    {
        return match($status) {
            TaskStatus::PENDING => new PendingState(),
            TaskStatus::COMPLETED => new CompletedState(),
            TaskStatus::APPROVED => new ApprovedState(),
            TaskStatus::REJECTED => new RejectedState(),
        };
    }
}
