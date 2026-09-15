<?php

declare(strict_types=1);

namespace App\Allowance\Domain\Service;

use App\Allowance\Domain\Entity\AllowanceRule;
use App\Allowance\Domain\ValueObject\Settlement;
use App\Allowance\Domain\ValueObject\SettlementLine;

final readonly class AllowanceCalculator
{
    /**
     * @param AllowanceRule[] $rules
     * @param array<string, int> $pointsPerAccount
     */
    public function settle(array $rules, array $pointsPerAccount): Settlement
    {
        $lines = [];

        foreach ($rules as $rule) {
            if (!$rule->isActive()) {
                continue;
            }

            $account = $rule->pointsAccount()->value;
            $points = $pointsPerAccount[$account] ?? 0;

            $lines[] = new SettlementLine(
                $account,
                $points,
                $rule->minimumPoints(),
                $rule->earnedOn($points)
            );
        }

        return Settlement::of($lines);
    }
}
