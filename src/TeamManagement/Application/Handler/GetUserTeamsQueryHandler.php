<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Query\GetUserTeamsQuery;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetUserTeamsQueryHandler
{
    public function __construct(
        private readonly TeamRepositoryInterface $teamRepository,
        private readonly TeamMembershipRepositoryInterface $memberships
    ) {
    }

    /**
     * @return Team[]
     */
    public function __invoke(GetUserTeamsQuery $query): array
    {
        $teams = array_map(
            fn (TeamMembership $membership) => $this->teamRepository->findById($membership->teamId()),
            $this->memberships->ofUser(Uuid::fromString($query->userId))
        );

        return array_values(array_filter($teams));
    }
}
