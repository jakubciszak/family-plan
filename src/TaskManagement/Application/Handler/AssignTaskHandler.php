<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\AssignTaskCommand;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AssignTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    public function __invoke(AssignTaskCommand $command): void
    {
        $task = $this->taskRepository->findById(Uuid::fromString($command->taskId));

        if (!$task) {
            throw new \DomainException('Task not found');
        }

        $task->assignTo(Uuid::fromString($command->userId));
        $this->taskRepository->save($task);
    }
}
