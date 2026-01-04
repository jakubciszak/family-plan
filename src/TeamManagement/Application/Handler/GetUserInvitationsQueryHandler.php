<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Handler;

use App\TeamManagement\Application\Query\GetUserInvitationsQuery;
use App\TeamManagement\Domain\Entity\TeamInvitation;
use App\TeamManagement\Domain\Repository\TeamInvitationRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetUserInvitationsQueryHandler
{
    public function __construct(
        private readonly TeamInvitationRepositoryInterface $invitationRepository
    ) {
    }

    /**
     * @return TeamInvitation[]
     */
    public function __invoke(GetUserInvitationsQuery $query): array
    {
        $email = Email::fromString($query->email);
        return $this->invitationRepository->findPendingByEmail($email);
    }
}
