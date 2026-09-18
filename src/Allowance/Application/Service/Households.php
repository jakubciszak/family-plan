<?php

declare(strict_types=1);

namespace App\Allowance\Application\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;

final readonly class Households
{
    public function __construct(private TeamMembershipRepositoryInterface $memberships)
    {
    }

    public function teamOf(Uuid $userId): ?Uuid
    {
        $memberships = $this->orderedMemberships($userId);

        return $memberships === [] ? null : $memberships[0]->teamId();
    }

    private function orderedMemberships(Uuid $userId): array
    {
        $memberships = $this->memberships->ofUser($userId);

        usort($memberships, static fn (TeamMembership $a, TeamMembership $b) => [
            $a->isAdmin(),
            $b->joinedAt(),
            $a->teamId()->value(),
        ] <=> [
            $b->isAdmin(),
            $a->joinedAt(),
            $b->teamId()->value(),
        ]);

        return $memberships;
    }

    /**
     * The team through which an admin looks after a member, if there is one.
     */
    public function sharedWithAdmin(Uuid $adminId, Uuid $memberId): ?Uuid
    {
        foreach ($this->orderedMemberships($memberId) as $membership) {
            if ($this->memberships->isAdmin($adminId, $membership->teamId())) {
                return $membership->teamId();
            }
        }

        return null;
    }

    /**
     * @return Uuid[] members an admin looks after, admins left out
     */
    public function membersLookedAfterBy(Uuid $adminId): array
    {
        $members = [];

        foreach ($this->memberships->ofUser($adminId) as $own) {
            if (!$this->memberships->isAdmin($adminId, $own->teamId())) {
                continue;
            }

            foreach ($this->memberships->ofTeam($own->teamId()) as $membership) {
                if (!$membership->isAdmin()) {
                    $members[$membership->userId()->value()] = $membership->userId();
                }
            }
        }

        return array_values($members);
    }
}
