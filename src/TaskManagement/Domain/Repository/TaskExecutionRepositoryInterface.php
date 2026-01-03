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

    /**
     * Find recent approved executions for a specific user and task template
     * @param Uuid $userId
     * @param Uuid $taskTemplateId
     * @param int $limit Maximum number of executions to return
     * @return TaskExecution[]
     */
    public function findRecentApprovedByUserAndTemplate(Uuid $userId, Uuid $taskTemplateId, int $limit = 30): array;

    /**
     * Count approved executions in the current month for a specific user
     * @param Uuid $userId
     * @return int
     */
    public function countApprovedInCurrentMonth(Uuid $userId): int;

    /**
     * Find approved executions for a specific user, task template and date
     * @param Uuid $userId
     * @param Uuid $taskTemplateId
     * @param DateTimeImmutable $date
     * @return TaskExecution[]
     */
    public function findApprovedByUserTemplateAndDate(Uuid $userId, Uuid $taskTemplateId, DateTimeImmutable $date): array;
}
