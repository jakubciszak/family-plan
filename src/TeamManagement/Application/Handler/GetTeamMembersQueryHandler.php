<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Query\GetTeamMembersQuery;
use App\TeamManagement\Domain\Entity\TeamMember;
use App\TeamManagement\Domain\Repository\TeamMemberRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetTeamMembersQueryHandler
{
    public function __construct(
        private readonly TeamMemberRepositoryInterface $teamMemberRepository
    ) {
    }

    /**
     * @return TeamMember[]
     */
    public function __invoke(GetTeamMembersQuery $query): array
    {
        $teamId = Uuid::fromString($query->teamId);
        return $this->teamMemberRepository->findByTeamId($teamId);
    }
}
