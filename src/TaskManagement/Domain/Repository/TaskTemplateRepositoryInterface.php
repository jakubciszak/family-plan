<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskTemplate;

interface TaskTemplateRepositoryInterface
{
    public function save(TaskTemplate $taskTemplate): void;

    public function findById(Uuid $id): ?TaskTemplate;

    public function findAll(): array;

    public function findByAssignedUser(Uuid $userId): array;

    public function findActive(): array;

    public function delete(TaskTemplate $taskTemplate): void;
}
