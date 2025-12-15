<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\CreateTaskTemplateCommand;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;

final readonly class CreateTaskTemplateHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    public function __invoke(CreateTaskTemplateCommand $command): void
    {
        $task = Task::createTemplate(
            Uuid::fromString($command->id),
            TaskName::fromString($command->name),
            $command->description,
            Points::fromInt($command->points),
            Frequency::fromString($command->frequency),
            ScheduleConfig::fromArray($command->scheduleConfig),
            $command->assignedUserId ? Uuid::fromString($command->assignedUserId) : null
        );

        $this->taskRepository->save($task);
    }
}
