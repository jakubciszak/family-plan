<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\ValueObject;

enum EntrySource: string
{
    case TASK_EXECUTION = 'task_execution';
    case BONUS_RULE = 'bonus_rule';
    case OPENING_BALANCE = 'opening_balance';
    case ADJUSTMENT = 'adjustment';
}
