<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\ValueObject;

enum RuleType: string
{
    case CONSECUTIVE_DAYS = 'consecutive_days';
    case MONTHLY_TASK_COUNT = 'monthly_task_count';
}
