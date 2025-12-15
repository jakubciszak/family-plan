<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final readonly class TaskExecutionCreated implements DomainEvent
{
    public function __construct(
        private Uuid $executionId,
        private ?Uuid $routineTaskId,
        private DateTimeImmutable $scheduledFor,
        private DateTimeImmutable $occurredOn
    ) {
    }

    public function executionId(): Uuid
    {
        return $this->executionId;
    }

    public function routineTaskId(): ?Uuid
    {
        return $this->routineTaskId;
    }

    public function scheduledFor(): DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'task_execution.created';
    }

    public function toPrimitives(): array
    {
        return [
            'execution_id' => $this->executionId->value(),
            'routine_task_id' => $this->routineTaskId?->value(),
            'scheduled_for' => $this->scheduledFor->format(DateTimeImmutable::ATOM),
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
        ];
    }
}
