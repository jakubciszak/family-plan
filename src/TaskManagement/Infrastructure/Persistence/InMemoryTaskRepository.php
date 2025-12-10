<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\TaskStatus;

final class InMemoryTaskRepository implements TaskRepositoryInterface
{
    /** @var array<string, Task> */
    private array $tasks = [];

    public function save(Task $task): void
    {
        $this->tasks[$task->id()->value()] = $task;
    }

    public function findById(Uuid $id): ?Task
    {
        return $this->tasks[$id->value()] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->tasks);
    }

    public function findByAssignedUser(Uuid $userId): array
    {
        return array_values(array_filter(
            $this->tasks,
            fn(Task $task) => $task->assignedUserId() !== null && $task->assignedUserId()->equals($userId)
        ));
    }

    public function findPending(): array
    {
        return array_values(array_filter(
            $this->tasks,
            fn(Task $task) => $task->status() === TaskStatus::PENDING
        ));
    }

    public function findCompleted(): array
    {
        return array_values(array_filter(
            $this->tasks,
            fn(Task $task) => $task->status() === TaskStatus::COMPLETED
        ));
    }

    public function delete(Task $task): void
    {
        unset($this->tasks[$task->id()->value()]);
    }

    public function clear(): void
    {
        $this->tasks = [];
    }
}
