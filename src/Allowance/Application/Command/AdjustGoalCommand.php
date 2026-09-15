<?php

declare(strict_types=1);

namespace App\Allowance\Application\Command;

final readonly class AdjustGoalCommand
{
    public function __construct(
        public string $goalId,
        public string $name,
        public int $target,
        public ?string $wantedBy
    ) {
    }
}
