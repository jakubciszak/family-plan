<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskExecution;
use DateTimeImmutable;

interface TaskExecutionRepositoryInterface
{
    public function save(TaskExecution $execution): void;

    public function findById(Uuid $id): ?TaskExecution;

    public function findAll(): array;

    public function findByRoutineTask(Uuid $routineTaskId): array;

    public function findByAssignedUser(Uuid $userId): array;

    public function findPending(): array;

    public function findCompleted(): array;

    public function findScheduledForDate(DateTimeImmutable $date): array;

    public function delete(TaskExecution $execution): void;
}
