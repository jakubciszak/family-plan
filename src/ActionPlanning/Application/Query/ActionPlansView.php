<?php

declare(strict_types=1);

namespace App\ActionPlanning\Application\Query;

use App\ActionPlanning\Domain\Entity\ActionPlan;
use App\ActionPlanning\Application\Service\ActionPlanAccess;
use App\ActionPlanning\Domain\Repository\ActionPlanRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class ActionPlansView
{
    public function __construct(private ActionPlanRepositoryInterface $plans, private ActionPlanAccess $access)
    {
    }

    public function ofUser(Uuid $userId): array
    {
        return array_map(fn (ActionPlan $plan): array => [...$plan->describe(),
            'canManage' => $this->access->canManage($plan, $userId),
            'canChangeScope' => $plan->userId()->equals($userId),
        ], $this->plans->visibleTo($userId, $this->access->teamIds($userId)));
    }
}
