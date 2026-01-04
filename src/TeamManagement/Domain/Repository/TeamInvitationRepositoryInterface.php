<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\TeamInvitation;
use App\UserManagement\Domain\ValueObject\Email;

interface TeamInvitationRepositoryInterface
{
    public function save(TeamInvitation $invitation): void;
    
    public function findById(Uuid $id): ?TeamInvitation;
    
    public function findByToken(string $token): ?TeamInvitation;
    
    /**
     * @return TeamInvitation[]
     */
    public function findByTeamId(Uuid $teamId): array;
    
    /**
     * @return TeamInvitation[]
     */
    public function findByEmail(Email $email): array;
    
    /**
     * @return TeamInvitation[]
     */
    public function findPendingByEmail(Email $email): array;
    
    public function remove(TeamInvitation $invitation): void;
}
