<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\CreateTaskExecutionCommand;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use DateTimeImmutable;

final readonly class CreateTaskExecutionHandler
{
    public function __construct(
        private TaskExecutionRepositoryInterface $taskExecutionRepository
    ) {
    }

    public function __invoke(CreateTaskExecutionCommand $command): void
    {
        $scheduledFor = new DateTimeImmutable($command->scheduledFor);
        $assignedUserId = $command->assignedUserId ? Uuid::fromString($command->assignedUserId) : null;

        if ($command->routineTaskId !== null) {
            // Create execution from routine task
            $execution = TaskExecution::createFromRoutineTask(
                Uuid::fromString($command->id),
                Uuid::fromString($command->routineTaskId),
                $scheduledFor,
                $assignedUserId
            );
        } else {
            // Create one-time execution
            if ($command->name === null || $command->description === null || $command->points === null) {
                throw new \InvalidArgumentException(
                    'One-time task executions require name, description, and points'
                );
            }

            $execution = TaskExecution::createOneTime(
                Uuid::fromString($command->id),
                TaskName::fromString($command->name),
                $command->description,
                Points::fromInt($command->points),
                $scheduledFor,
                $assignedUserId
            );
        }

        $this->taskExecutionRepository->save($execution);
    }
}
