<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use DomainException;

final readonly class ApprovedExecutionState implements ExecutionStateInterface
{
    public function complete(TaskExecution $execution, Uuid $userId): void
    {
        throw new DomainException('Cannot complete an already approved task execution');
    }

    public function approve(TaskExecution $execution, Uuid $adminId): void
    {
        throw new DomainException('Task execution is already approved');
    }

    public function reject(TaskExecution $execution): void
    {
        throw new DomainException('Cannot reject an approved task execution');
    }
}
