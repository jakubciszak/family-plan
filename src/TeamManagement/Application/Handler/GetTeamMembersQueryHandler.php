<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Query\GetTeamMembersQuery;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetTeamMembersQueryHandler
{
    public function __construct(
        private readonly TeamMembershipRepositoryInterface $memberships
    ) {
    }

    /**
     * @return TeamMembership[]
     */
    public function __invoke(GetTeamMembersQuery $query): array
    {
        $teamId = Uuid::fromString($query->teamId);
        return $this->memberships->ofTeam($teamId);
    }
}
