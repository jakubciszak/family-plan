<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final readonly class TaskExecutionApproved implements DomainEvent
{
    public function __construct(
        private Uuid $executionId,
        private Uuid $adminId,
        private DateTimeImmutable $approvedAt
    ) {
    }

    public function executionId(): Uuid
    {
        return $this->executionId;
    }

    public function adminId(): Uuid
    {
        return $this->adminId;
    }

    public function approvedAt(): DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function eventName(): string
    {
        return 'task_execution.approved';
    }

    public function toPrimitives(): array
    {
        return [
            'execution_id' => $this->executionId->value(),
            'admin_id' => $this->adminId->value(),
            'approved_at' => $this->approvedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
