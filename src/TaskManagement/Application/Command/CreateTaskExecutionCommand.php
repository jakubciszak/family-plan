<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Command;

final readonly class CreateTaskExecutionCommand
{
    public function __construct(
        public string $id,
        public ?string $templateTaskId,
        public ?string $name,
        public ?string $description,
        public ?int $points,
        public string $scheduledFor,
        public ?string $assignedUserId = null
    ) {
    }
}
