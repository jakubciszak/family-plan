<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Application\Service;

use App\SchoolTimetable\Domain\Exception\TimetableException;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;

final readonly class TimetableAccess
{
    public function __construct(private TeamMembershipRepositoryInterface $memberships)
    {
    }

    public function team(Uuid $caller, mixed $teamId): Uuid
    {
        if (!is_string($teamId) || !Uuid::isValid(strtolower($teamId))) {
            throw TimetableException::invalid('A team identifier is required.');
        }

        $team = Uuid::fromString(strtolower($teamId));
        if (!$this->memberships->isAdmin($caller, $team)) {
            throw TimetableException::denied();
        }

        return $team;
    }

    public function member(Uuid $userId, Uuid $teamId): bool
    {
        return $this->memberships->isMember($userId, $teamId);
    }

    public function anyAdmin(Uuid $teamId): ?Uuid
    {
        foreach ($this->memberships->ofTeam($teamId) as $membership) {
            if ($membership->role()->value === 'admin') {
                return $membership->userId();
            }
        }

        return null;
    }
}
