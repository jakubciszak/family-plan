<?php

declare(strict_types=1);

namespace App\ActionPlanning\Domain\Repository;

use App\ActionPlanning\Domain\Entity\ActionPlan;
use App\Shared\Domain\ValueObject\Uuid;

interface ActionPlanRepositoryInterface
{
    public function visibleTo(Uuid $userId, array $teamIds): array;
    public function find(Uuid $id): ?ActionPlan;
    public function isLinked(Uuid $id): bool;
    public function save(ActionPlan $plan): void;
    public function remove(ActionPlan $plan): void;
}
