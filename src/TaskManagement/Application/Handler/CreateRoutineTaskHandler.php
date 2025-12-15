<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\CreateRoutineTaskCommand;
use App\TaskManagement\Domain\Entity\RoutineTask;
use App\TaskManagement\Domain\Repository\RoutineTaskRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;

final readonly class CreateRoutineTaskHandler
{
    public function __construct(
        private RoutineTaskRepositoryInterface $routineTaskRepository
    ) {
    }

    public function __invoke(CreateRoutineTaskCommand $command): void
    {
        $routineTask = RoutineTask::create(
            Uuid::fromString($command->id),
            TaskName::fromString($command->name),
            $command->description,
            Points::fromInt($command->points),
            Frequency::fromString($command->frequency),
            ScheduleConfig::fromArray($command->scheduleConfig),
            $command->assignedUserId ? Uuid::fromString($command->assignedUserId) : null
        );

        $this->routineTaskRepository->save($routineTask);
    }
}
