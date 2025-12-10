<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;

final readonly class ApproveTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    public function __invoke(ApproveTaskCommand $command): void
    {
        $task = $this->taskRepository->findById(Uuid::fromString($command->taskId));

        if (!$task) {
            throw new \RuntimeException('Task not found');
        }

        $task->approve(Uuid::fromString($command->adminId));

        $this->taskRepository->save($task);
    }
}
