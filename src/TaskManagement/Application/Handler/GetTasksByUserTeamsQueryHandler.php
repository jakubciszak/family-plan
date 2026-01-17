<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Query\GetTasksByUserTeamsQuery;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetTasksByUserTeamsQueryHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private TeamRepositoryInterface $teamRepository
    ) {
    }

    /**
     * Get tasks filtered by user's team membership
     *
     * @return Task[]
     */
    public function __invoke(GetTasksByUserTeamsQuery $query): array
    {
        $userId = Uuid::fromString($query->userId);

        // Get all teams the user is a member of
        $userTeams = $this->teamRepository->findByUserId($userId);

        if (empty($userTeams)) {
            return [];
        }

        $teamIds = array_map(fn($team) => $team->id(), $userTeams);

        // If a specific teamId is requested, verify user is member and filter by that team only
        if ($query->teamId !== null) {
            $requestedTeamId = Uuid::fromString($query->teamId);

            // Check if user is member of the requested team
            $isMember = false;
            foreach ($teamIds as $teamId) {
                if ($teamId->equals($requestedTeamId)) {
                    $isMember = true;
                    break;
                }
            }

            // If user is not a member, return empty array (access denied)
            if (!$isMember) {
                return [];
            }

            // Return tasks from the requested team only
            return $this->taskRepository->findByTeamId($requestedTeamId);
        }

        // Return tasks from all user's teams
        return $this->taskRepository->findByTeamIds($teamIds);
    }
}
