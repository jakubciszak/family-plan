<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\TeamMember;

interface TeamMemberRepositoryInterface
{
    public function save(TeamMember $member): void;
    
    public function findById(Uuid $id): ?TeamMember;
    
    public function findByTeamIdAndUserId(Uuid $teamId, Uuid $userId): ?TeamMember;
    
    /**
     * @return TeamMember[]
     */
    public function findByTeamId(Uuid $teamId): array;
    
    /**
     * @return TeamMember[]
     */
    public function findByUserId(Uuid $userId): array;
    
    public function remove(TeamMember $member): void;
    
    public function isUserMemberOfTeam(Uuid $userId, Uuid $teamId): bool;
    
    public function isUserAdminOfTeam(Uuid $userId, Uuid $teamId): bool;
}
