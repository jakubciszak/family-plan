<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use DateTimeImmutable;

final readonly class RoutineTaskCreated implements DomainEvent
{
    public function __construct(
        private Uuid $routineTaskId,
        private TaskName $name,
        private Points $points,
        private Frequency $frequency,
        private ScheduleConfig $scheduleConfig,
        private DateTimeImmutable $occurredOn
    ) {
    }

    public function routineTaskId(): Uuid
    {
        return $this->routineTaskId;
    }

    public function taskName(): TaskName
    {
        return $this->name;
    }

    public function points(): Points
    {
        return $this->points;
    }

    public function frequency(): Frequency
    {
        return $this->frequency;
    }

    public function scheduleConfig(): ScheduleConfig
    {
        return $this->scheduleConfig;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'routine_task.created';
    }

    public function toPrimitives(): array
    {
        return [
            'routine_task_id' => $this->routineTaskId->value(),
            'name' => $this->name->value(),
            'points' => $this->points->value(),
            'frequency' => $this->frequency->value,
            'schedule_config' => $this->scheduleConfig->toArray(),
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
        ];
    }
}
