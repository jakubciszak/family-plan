<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\RoutineTask;

interface RoutineTaskRepositoryInterface
{
    public function save(RoutineTask $routineTask): void;

    public function findById(Uuid $id): ?RoutineTask;

    public function findAll(): array;

    public function findActive(): array;

    public function findByAssignedUser(Uuid $userId): array;

    public function delete(RoutineTask $routineTask): void;
}
