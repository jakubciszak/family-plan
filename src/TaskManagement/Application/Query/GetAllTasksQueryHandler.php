<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Query;

use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;

final readonly class GetAllTasksQueryHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    public function __invoke(GetAllTasksQuery $query): array
    {
        return $this->taskRepository->findAll();
    }
}
