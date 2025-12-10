<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final readonly class TaskCompleted implements DomainEvent
{
    public function __construct(
        private Uuid $taskId,
        private Uuid $userId,
        private DateTimeImmutable $occurredOn
    ) {
    }

    public function taskId(): Uuid
    {
        return $this->taskId;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'task.completed';
    }

    public function toPrimitives(): array
    {
        return [
            'task_id' => $this->taskId->value(),
            'user_id' => $this->userId->value(),
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
        ];
    }
}
