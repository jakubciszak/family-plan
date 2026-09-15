<?php

declare(strict_types=1);

namespace App\Allowance\Domain\ValueObject;

use InvalidArgumentException;

/**
 * How much money a batch of points is worth - "5 zloty for every 3 points".
 * Kept as a pair of whole numbers so any fraction converts exactly.
 */
final readonly class ConversionRate
{
    private function __construct(
        private Money $amount,
        private int $perPoints
    ) {
    }

    public static function of(Money $amount, int $perPoints): self
    {
        if ($amount->isNegative()) {
            throw new InvalidArgumentException('A rate cannot pay out a negative amount');
        }

        if ($perPoints < 1) {
            throw new InvalidArgumentException('A rate must be given for at least one point');
        }

        return new self($amount, $perPoints);
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function perPoints(): int
    {
        return $this->perPoints;
    }

    public function convert(int $points): Money
    {
        if ($points <= 0) {
            return Money::zero();
        }

        $numerator = $this->amount->minorUnits() * $points;

        return Money::fromMinorUnits(intdiv($numerator * 2 + $this->perPoints, $this->perPoints * 2));
    }
}
