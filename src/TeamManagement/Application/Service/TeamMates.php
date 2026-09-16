<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;

final readonly class TeamMates
{
    public function __construct(
        private TeamMembershipRepositoryInterface $memberships
    ) {
    }

    public function administersAnyTeam(Uuid $userId): bool
    {
        foreach ($this->memberships->ofUser($userId) as $membership) {
            if ($membership->isAdmin()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Uuid[]
     */
    public function administeredBy(Uuid $adminId): array
    {
        $mates = [];

        foreach ($this->memberships->ofUser($adminId) as $membership) {
            if (!$membership->isAdmin()) {
                continue;
            }

            foreach ($this->memberships->ofTeam($membership->teamId()) as $mate) {
                if (!$mate->userId()->equals($adminId)) {
                    $mates[$mate->userId()->value()] = $mate->userId();
                }
            }
        }

        return array_values($mates);
    }

    public function isAdministeredBy(Uuid $userId, Uuid $adminId): bool
    {
        foreach ($this->administeredBy($adminId) as $mate) {
            if ($mate->equals($userId)) {
                return true;
            }
        }

        return false;
    }
}
