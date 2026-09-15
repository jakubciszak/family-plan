<?php

declare(strict_types=1);

namespace App\Allowance\Application\Command;

final readonly class SetAllowanceRuleCommand
{
    public function __construct(
        public string $teamId,
        public string $pointsAccount,
        public int $minimumPoints,
        public int $rateAmount,
        public int $ratePerPoints
    ) {
    }
}
