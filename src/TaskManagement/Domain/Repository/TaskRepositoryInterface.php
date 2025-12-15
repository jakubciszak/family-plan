<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\Task;

interface TaskRepositoryInterface
{
    public function save(Task $task): void;

    public function findById(Uuid $id): ?Task;

    public function findAll(): array;

    public function findByAssignedUser(Uuid $userId): array;

    public function findPending(): array;

    public function findCompleted(): array;

    public function findTemplates(): array;

    public function findActiveTemplates(): array;

    public function delete(Task $task): void;
}
