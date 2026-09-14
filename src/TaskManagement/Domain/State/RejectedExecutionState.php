<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use DomainException;

final readonly class RejectedExecutionState implements ExecutionStateInterface
{
    public function complete(TaskExecution $execution, Uuid $userId, ClockInterface $clock): void
    {
        $execution->transitionToState(new CompletedExecutionState(), $userId, $clock);
    }

    public function approve(TaskExecution $execution, Uuid $adminId, ClockInterface $clock): void
    {
        throw new DomainException('Cannot approve a rejected task execution');
    }

    public function reject(TaskExecution $execution): void
    {
        throw new DomainException('Task execution is already rejected');
    }
}
