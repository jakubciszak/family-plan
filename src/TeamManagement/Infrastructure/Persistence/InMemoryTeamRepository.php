<?php

declare(strict_types=1);

namespace App\TeamManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;

final class InMemoryTeamRepository implements TeamRepositoryInterface
{
    /**
     * @var array<string, Team>
     */
    private array $teams = [];

    public function save(Team $team): void
    {
        $this->teams[$team->id()->value()] = $team;
    }

    public function findById(Uuid $id): ?Team
    {
        return $this->teams[$id->value()] ?? null;
    }

    /**
     * @return Team[]
     */
    public function findAll(): array
    {
        return array_values($this->teams);
    }

    /**
     * @return Team[]
     */
    public function findByCreator(Uuid $creatorId): array
    {
        return array_values(array_filter(
            $this->teams,
            fn(Team $team) => $team->createdBy()->value() === $creatorId->value()
        ));
    }

    /**
     * @return Team[]
     */
    public function findByUserId(Uuid $userId): array
    {
        // In memory implementation - will return all teams
        // In real implementation, this would query team_members table
        return array_values($this->teams);
    }

    public function remove(Team $team): void
    {
        unset($this->teams[$team->id()->value()]);
    }
}
