<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use DomainException;

final readonly class CompletedExecutionState implements ExecutionStateInterface
{
    public function complete(TaskExecution $execution, Uuid $userId, ClockInterface $clock): void
    {
        throw new DomainException('Task execution is already completed');
    }

    public function approve(TaskExecution $execution, Uuid $adminId, ClockInterface $clock): void
    {
        $execution->transitionToApproved($adminId, $clock);
    }

    public function reject(TaskExecution $execution): void
    {
        $execution->transitionToRejected();
    }
}
