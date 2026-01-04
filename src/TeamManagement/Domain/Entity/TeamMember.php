<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\ValueObject\Email;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'team_members')]
#[ORM\Index(columns: ['team_id'])]
#[ORM\Index(columns: ['user_id'])]
class TeamMember
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        
        #[ORM\Column(type: 'uuid')]
        private Uuid $teamId,
        
        #[ORM\Column(type: 'uuid')]
        private Uuid $userId,
        
        #[ORM\Column(type: 'string', length: 50)]
        private TeamRole $role,
        
        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $joinedAt
    ) {
    }

    public static function create(
        Uuid $id,
        Uuid $teamId,
        Uuid $userId,
        TeamRole $role
    ): self {
        return new self(
            $id,
            $teamId,
            $userId,
            $role,
            new DateTimeImmutable()
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function teamId(): Uuid
    {
        return $this->teamId;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function role(): TeamRole
    {
        return $this->role;
    }

    public function joinedAt(): DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function changeRole(TeamRole $newRole): void
    {
        $this->role = $newRole;
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }
}
