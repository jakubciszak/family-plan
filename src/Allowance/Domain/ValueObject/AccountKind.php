<?php

declare(strict_types=1);

namespace App\Allowance\Domain\ValueObject;

enum AccountKind: string
{
    case PENDING = 'pending';
    case AVAILABLE = 'available';
    case GOAL = 'goal';
    case EARNINGS = 'earnings';
    case INCOME = 'income';
    case EXPENSES = 'expenses';

    public function mustStayPositive(): bool
    {
        return match ($this) {
            self::PENDING, self::AVAILABLE, self::GOAL => true,
            default => false,
        };
    }

    public function isHeldMoney(): bool
    {
        return $this->mustStayPositive();
    }

    public function needsReference(): bool
    {
        return $this === self::GOAL;
    }
}
