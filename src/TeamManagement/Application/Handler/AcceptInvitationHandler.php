<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Command\AcceptInvitationCommand;
use App\TeamManagement\Domain\Exception\InvitationNotFoundException;
use App\TeamManagement\Domain\Exception\UnauthorizedTeamActionException;
use App\TeamManagement\Domain\Repository\TeamInvitationRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AcceptInvitationHandler
{
    public function __construct(
        private readonly TeamInvitationRepositoryInterface $invitationRepository,
        private readonly TeamMembershipRepositoryInterface $memberships,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function __invoke(AcceptInvitationCommand $command): void
    {
        $invitation = $this->invitationRepository->findByToken($command->token);
        
        if ($invitation === null) {
            throw new InvitationNotFoundException($command->token);
        }

        $userId = Uuid::fromString($command->userId);
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new UnauthorizedTeamActionException('Only the invited account can accept this invitation');
        }

        if (!$user->email()->equals($invitation->email())) {
            throw new UnauthorizedTeamActionException(
                sprintf('This invitation was issued for %s', $invitation->email()->value())
            );
        }

        // Accept invitation (this validates it's pending and not expired)
        $invitation->accept();
        $this->invitationRepository->save($invitation);

        if ($this->memberships->find($invitation->teamId(), $userId) !== null) {
            return;
        }

        $this->memberships->join($invitation->teamId(), $userId, $invitation->role());
    }
}
