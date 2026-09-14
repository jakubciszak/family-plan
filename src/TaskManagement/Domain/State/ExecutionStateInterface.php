<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use DateTimeImmutable;

interface ExecutionStateInterface
{
    public function complete(
        TaskExecution $execution,
        Uuid $userId,
        ClockInterface $clock,
        ?DateTimeImmutable $doneOn = null
    ): void;
    public function approve(TaskExecution $execution, Uuid $adminId, ClockInterface $clock): void;
    public function reject(TaskExecution $execution): void;
}
