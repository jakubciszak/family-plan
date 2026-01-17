<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Query;

final readonly class GetTasksByUserTeamsQuery
{
    public function __construct(
        public string $userId,
        public ?string $teamId = null
    ) {
    }
}
