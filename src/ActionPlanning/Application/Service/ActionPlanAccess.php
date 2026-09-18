<?php

declare(strict_types=1);

namespace App\ActionPlanning\Application\Service;

use App\ActionPlanning\Domain\Entity\ActionPlan;
use App\ActionPlanning\Domain\Exception\ActionPlanNotFound;
use App\ActionPlanning\Domain\Repository\ActionPlanRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Exception\UnauthorizedTeamActionException;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;

final readonly class ActionPlanAccess
{
    public function __construct(private ActionPlanRepositoryInterface $plans, private TeamMembershipRepositoryInterface $memberships)
    {
    }

    public function assertMember(Uuid $caller, ?Uuid $teamId): void
    {
        if ($teamId !== null && !$this->memberships->isMember($caller, $teamId)) {
            throw new UnauthorizedTeamActionException('actionPlans.teamAccessDenied');
        }
    }

    public function canManage(ActionPlan $plan, Uuid $caller): bool
    {
        if ($plan->teamId() === null) {
            return $plan->userId()->equals($caller);
        }

        return $this->memberships->isMember($caller, $plan->teamId())
            && ($plan->userId()->equals($caller) || $this->memberships->isAdmin($caller, $plan->teamId()));
    }

    public function forManagement(Uuid $id, Uuid $caller): ActionPlan
    {
        $plan = $this->plans->find($id);
        if ($plan === null || !$this->canManage($plan, $caller)) {
            throw new ActionPlanNotFound();
        }

        return $plan;
    }

    public function forTaskType(?Uuid $id, Uuid $teamId): ?array
    {
        if ($id === null) {
            return null;
        }
        $plan = $this->plans->find($id);
        if ($plan === null || !$plan->teamId()?->equals($teamId)) {
            throw new \DomainException('actionPlans.sameTeamRequired');
        }

        return $plan->describe();
    }

    public function teamIds(Uuid $caller): array
    {
        return array_map(static fn ($membership): string => $membership->teamId()->value(), $this->memberships->ofUser($caller));
    }
}
