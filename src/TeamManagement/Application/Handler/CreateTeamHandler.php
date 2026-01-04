<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Command\CreateTeamCommand;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\Entity\TeamMember;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamMemberRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamName;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateTeamHandler
{
    public function __construct(
        private readonly TeamRepositoryInterface $teamRepository,
        private readonly TeamMemberRepositoryInterface $teamMemberRepository
    ) {
    }

    public function __invoke(CreateTeamCommand $command): void
    {
        $teamId = Uuid::fromString($command->teamId);
        $createdBy = Uuid::fromString($command->createdBy);
        
        $team = Team::create(
            $teamId,
            TeamName::fromString($command->name),
            $command->description,
            $createdBy
        );
        
        $this->teamRepository->save($team);
        
        // Automatically add creator as admin
        $member = TeamMember::create(
            Uuid::generate(),
            $teamId,
            $createdBy,
            TeamRole::admin()
        );
        
        $this->teamMemberRepository->save($member);
    }
}
