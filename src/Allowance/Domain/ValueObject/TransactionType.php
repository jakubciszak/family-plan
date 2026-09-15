<?php

declare(strict_types=1);

namespace App\Allowance\Domain\ValueObject;

enum TransactionType: string
{
    case WEEK_CLOSED = 'week_closed';
    case WEEK_REOPENED = 'week_reopened';
    case PAYOUT = 'payout';
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case GOAL_ALLOCATION = 'goal_allocation';
    case GOAL_RELEASE = 'goal_release';
    case GOAL_SPENDING = 'goal_spending';
}
