<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\ValueObject\TeamRole;

interface TeamMembershipRepositoryInterface
{
    public function join(Uuid $teamId, Uuid $userId, TeamRole $role): ?TeamMembership;

    public function leave(Uuid $teamId, Uuid $userId): void;

    public function find(Uuid $teamId, Uuid $userId): ?TeamMembership;

    /**
     * @return TeamMembership[]
     */
    public function ofTeam(Uuid $teamId): array;

    /**
     * @return TeamMembership[]
     */
    public function ofUser(Uuid $userId): array;

    public function isMember(Uuid $userId, Uuid $teamId): bool;

    public function isAdmin(Uuid $userId, Uuid $teamId): bool;
}
