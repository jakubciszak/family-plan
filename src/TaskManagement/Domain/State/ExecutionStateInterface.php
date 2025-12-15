<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\State;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;

interface ExecutionStateInterface
{
    public function complete(TaskExecution $execution, Uuid $userId): void;
    public function approve(TaskExecution $execution, Uuid $adminId): void;
    public function reject(TaskExecution $execution): void;
}
