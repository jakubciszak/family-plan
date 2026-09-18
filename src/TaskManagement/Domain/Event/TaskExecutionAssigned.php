<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final readonly class TaskExecutionAssigned implements DomainEvent
{
    public function __construct(private Uuid $executionId, private DateTimeImmutable $occurredAt)
    {
    }

    public function executionId(): Uuid
    {
        return $this->executionId;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'task_execution.assigned';
    }

    public function toPrimitives(): array
    {
        return ['execution_id' => $this->executionId->value(), 'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM)];
    }
}
