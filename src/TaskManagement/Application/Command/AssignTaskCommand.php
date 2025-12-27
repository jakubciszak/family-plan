<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Command;

final readonly class AssignTaskCommand
{
    public function __construct(
        public string $taskId,
        public string $userId
    ) {
    }
}
