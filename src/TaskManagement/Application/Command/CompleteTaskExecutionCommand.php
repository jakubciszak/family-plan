<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Command;

final readonly class CompleteTaskExecutionCommand
{
    public function __construct(
        public string $executionId,
        public string $userId
    ) {
    }
}
