<?php

declare(strict_types=1);

namespace App\TeamManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\TeamMember;
use App\TeamManagement\Domain\Repository\TeamMemberRepositoryInterface;

final class InMemoryTeamMemberRepository implements TeamMemberRepositoryInterface
{
    /**
     * @var array<string, TeamMember>
     */
    private array $members = [];

    public function save(TeamMember $member): void
    {
        $this->members[$member->id()->value()] = $member;
    }

    public function findById(Uuid $id): ?TeamMember
    {
        return $this->members[$id->value()] ?? null;
    }

    public function findByTeamIdAndUserId(Uuid $teamId, Uuid $userId): ?TeamMember
    {
        foreach ($this->members as $member) {
            if ($member->teamId()->value() === $teamId->value()
                && $member->userId()->value() === $userId->value()) {
                return $member;
            }
        }
        return null;
    }

    /**
     * @return TeamMember[]
     */
    public function findByTeamId(Uuid $teamId): array
    {
        return array_values(array_filter(
            $this->members,
            fn(TeamMember $member) => $member->teamId()->value() === $teamId->value()
        ));
    }

    /**
     * @return TeamMember[]
     */
    public function findByUserId(Uuid $userId): array
    {
        return array_values(array_filter(
            $this->members,
            fn(TeamMember $member) => $member->userId()->value() === $userId->value()
        ));
    }

    public function remove(TeamMember $member): void
    {
        unset($this->members[$member->id()->value()]);
    }

    public function isUserMemberOfTeam(Uuid $userId, Uuid $teamId): bool
    {
        return $this->findByTeamIdAndUserId($teamId, $userId) !== null;
    }

    public function isUserAdminOfTeam(Uuid $userId, Uuid $teamId): bool
    {
        $member = $this->findByTeamIdAndUserId($teamId, $userId);
        return $member !== null && $member->isAdmin();
    }
}
