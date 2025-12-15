<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\ValueObject;

use InvalidArgumentException;

enum Frequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case ONCE = 'once';

    public static function fromString(string $value): self
    {
        return match(strtolower($value)) {
            'daily' => self::DAILY,
            'weekly' => self::WEEKLY,
            'monthly' => self::MONTHLY,
            'once' => self::ONCE,
            default => throw new InvalidArgumentException(sprintf('Invalid frequency: %s', $value))
        };
    }

    public function label(): string
    {
        return match($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
            self::ONCE => 'Once',
        };
    }
}
