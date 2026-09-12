<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Command\RemoveMemberCommand;
use App\TeamManagement\Domain\Exception\UnauthorizedTeamActionException;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RemoveMemberHandler
{
    public function __construct(
        private readonly TeamMembershipRepositoryInterface $memberships
    ) {
    }

    public function __invoke(RemoveMemberCommand $command): void
    {
        $teamId = Uuid::fromString($command->teamId);
        $userId = Uuid::fromString($command->userId);
        $removedBy = Uuid::fromString($command->removedBy);
        
        // Verify remover is admin of the team
        if (!$this->memberships->isAdmin($removedBy, $teamId)) {
            throw new UnauthorizedTeamActionException('Only team admins can remove members');
        }
        
        $this->memberships->leave($teamId, $userId);
    }
}
