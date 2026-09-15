<?php

declare(strict_types=1);

namespace App\Allowance\Application\Command;

final readonly class ReopenWeekCommand
{
    public function __construct(
        public string $userId,
        public string $weekStart
    ) {
    }
}
