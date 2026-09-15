<?php

declare(strict_types=1);

namespace App\Allowance\Application\Command;

final readonly class SpendGoalCommand
{
    public function __construct(
        public string $goalId,
        public int $amount,
        public string $description
    ) {
    }
}
