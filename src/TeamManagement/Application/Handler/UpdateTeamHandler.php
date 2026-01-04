<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Command\UpdateTeamCommand;
use App\TeamManagement\Domain\Exception\TeamNotFoundException;
use App\TeamManagement\Domain\Exception\UnauthorizedTeamActionException;
use App\TeamManagement\Domain\Repository\TeamMemberRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamName;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateTeamHandler
{
    public function __construct(
        private readonly TeamRepositoryInterface $teamRepository,
        private readonly TeamMemberRepositoryInterface $teamMemberRepository
    ) {
    }

    public function __invoke(UpdateTeamCommand $command): void
    {
        $teamId = Uuid::fromString($command->teamId);
        $updatedBy = Uuid::fromString($command->updatedBy);
        
        // Verify team exists
        $team = $this->teamRepository->findById($teamId);
        if ($team === null) {
            throw new TeamNotFoundException($command->teamId);
        }
        
        // Verify updater is admin of the team
        if (!$this->teamMemberRepository->isUserAdminOfTeam($updatedBy, $teamId)) {
            throw new UnauthorizedTeamActionException('Only team admins can update team details');
        }
        
        // Update team
        $team->updateName(TeamName::fromString($command->name));
        $team->updateDescription($command->description);
        
        $this->teamRepository->save($team);
    }
}
