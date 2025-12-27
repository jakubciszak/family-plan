<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Query;

final readonly class FindTaskByIdQuery
{
    public function __construct(
        public string $id
    ) {
    }
}
