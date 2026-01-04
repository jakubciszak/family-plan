<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Query\GetTeamInvitationsQuery;
use App\TeamManagement\Domain\Entity\TeamInvitation;
use App\TeamManagement\Domain\Repository\TeamInvitationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetTeamInvitationsQueryHandler
{
    public function __construct(
        private readonly TeamInvitationRepositoryInterface $invitationRepository
    ) {
    }

    /**
     * @return TeamInvitation[]
     */
    public function __invoke(GetTeamInvitationsQuery $query): array
    {
        $teamId = Uuid::fromString($query->teamId);
        return $this->invitationRepository->findByTeamId($teamId);
    }
}
