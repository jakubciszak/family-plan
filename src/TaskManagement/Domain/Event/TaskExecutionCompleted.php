<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final readonly class TaskExecutionCompleted implements DomainEvent
{
    public function __construct(
        private Uuid $executionId,
        private Uuid $userId,
        private DateTimeImmutable $completedAt
    ) {
    }

    public function executionId(): Uuid
    {
        return $this->executionId;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function completedAt(): DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function eventName(): string
    {
        return 'task_execution.completed';
    }

    public function toPrimitives(): array
    {
        return [
            'execution_id' => $this->executionId->value(),
            'user_id' => $this->userId->value(),
            'completed_at' => $this->completedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
