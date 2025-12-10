<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\Frequency;
use DateTimeImmutable;

final readonly class TaskCreated implements DomainEvent
{
    public function __construct(
        private Uuid $taskId,
        private TaskName $name,
        private Points $points,
        private Frequency $frequency,
        private DateTimeImmutable $occurredOn
    ) {
    }

    public function taskId(): Uuid
    {
        return $this->taskId;
    }

    public function name(): TaskName
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

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'task.created';
    }

    public function toPrimitives(): array
    {
        return [
            'task_id' => $this->taskId->value(),
            'name' => $this->name->value(),
            'points' => $this->points->value(),
            'frequency' => $this->frequency->value,
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
        ];
    }
}
