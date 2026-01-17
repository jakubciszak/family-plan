<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Command;

final readonly class CreateTaskCommand
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public int $points,
        public string $frequency,
        public string $createdBy,
        public ?string $teamId = null,
        public ?string $assignedUserId = null
    ) {
    }
}
