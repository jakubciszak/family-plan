<?php

declare(strict_types=1);

namespace App\Allowance\Application\Command;

final readonly class CloseWeekCommand
{
    public function __construct(
        public string $teamId,
        public string $userId,
        public string $weekStart,
        public string $closedBy
    ) {
    }
}
