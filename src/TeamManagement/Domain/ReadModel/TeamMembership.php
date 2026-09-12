<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\ReadModel;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use DateTimeImmutable;

final readonly class TeamMembership
{
    public function __construct(
        private Uuid $id,
        private Uuid $teamId,
        private Uuid $userId,
        private TeamRole $role,
        private DateTimeImmutable $joinedAt
    ) {
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

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }
}
