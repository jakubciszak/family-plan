<?php

declare(strict_types=1);

namespace App\Allowance\Domain\ValueObject;

final readonly class SettlementLine
{
    public function __construct(
        private string $pointsAccount,
        private int $points,
        private int $minimumPoints,
        private Money $amount
    ) {
    }

    public function pointsAccount(): string
    {
        return $this->pointsAccount;
    }

    public function points(): int
    {
        return $this->points;
    }

    public function minimumPoints(): int
    {
        return $this->minimumPoints;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function reachedMinimum(): bool
    {
        return $this->points >= $this->minimumPoints;
    }

    public function missingPoints(): int
    {
        return max(0, $this->minimumPoints - $this->points);
    }

    public function toArray(): array
    {
        return [
            'pointsAccount' => $this->pointsAccount,
            'points' => $this->points,
            'minimumPoints' => $this->minimumPoints,
            'amount' => $this->amount->minorUnits(),
            'reachedMinimum' => $this->reachedMinimum(),
            'missingPoints' => $this->missingPoints(),
        ];
    }
}
