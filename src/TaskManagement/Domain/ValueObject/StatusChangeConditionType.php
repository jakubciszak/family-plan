<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\ValueObject;

enum StatusChangeConditionType: string
{
    case OTHER_TASK_COMPLETED_TODAY = 'other_task_completed_today';
    case LAST_EXECUTION_COOLDOWN = 'last_execution_cooldown';
}
