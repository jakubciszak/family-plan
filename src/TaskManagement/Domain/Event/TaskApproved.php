<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final readonly class TaskApproved implements DomainEvent
{
    public function __construct(
        private Uuid $taskId,
        private Uuid $adminId,
        private DateTimeImmutable $occurredOn
    ) {
    }

    public function taskId(): Uuid
    {
        return $this->taskId;
    }

    public function adminId(): Uuid
    {
        return $this->adminId;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'task.approved';
    }

    public function toPrimitives(): array
    {
        return [
            'task_id' => $this->taskId->value(),
            'admin_id' => $this->adminId->value(),
            'occurred_on' => $this->occurredOn->format(DateTimeImmutable::ATOM),
        ];
    }
}
