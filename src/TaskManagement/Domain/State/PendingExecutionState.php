<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use DateTimeImmutable;
use DomainException;

final readonly class PendingExecutionState implements ExecutionStateInterface
{
    public function complete(
        TaskExecution $execution,
        Uuid $userId,
        ClockInterface $clock,
        ?DateTimeImmutable $doneOn = null
    ): void {
        $execution->transitionToState(new CompletedExecutionState(), $userId, $clock, $doneOn);
    }

    public function approve(TaskExecution $execution, Uuid $adminId, ClockInterface $clock): void
    {
        throw new DomainException('Only completed task executions can be approved');
    }

    public function reject(TaskExecution $execution): void
    {
        throw new DomainException('Only completed task executions can be rejected');
    }
}
