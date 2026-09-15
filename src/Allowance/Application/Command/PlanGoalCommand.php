<?php

declare(strict_types=1);

namespace App\Allowance\Application\Command;

final readonly class PlanGoalCommand
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $name,
        public int $target,
        public ?string $wantedBy
    ) {
    }
}
