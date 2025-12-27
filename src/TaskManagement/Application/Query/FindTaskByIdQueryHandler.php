<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Query;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class FindTaskByIdQueryHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    public function __invoke(FindTaskByIdQuery $query): ?Task
    {
        return $this->taskRepository->findById(Uuid::fromString($query->id));
    }
}
