<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Service;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Command\CreateTeamCommand;
use App\TeamManagement\Application\Handler\CreateTeamHandler;
use App\TeamManagement\Domain\Repository\TeamInvitationRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;

final readonly class DefaultTeamProvisioner
{
    public function __construct(
        private CreateTeamHandler $createTeam,
        private TeamInvitationRepositoryInterface $invitations,
        private string $defaultTeamName
    ) {
    }

    public function provisionFor(Uuid $userId, Email $email, ?string $inviteToken = null): void
    {
        if ($this->isJoining($inviteToken)) {
            return;
        }

        if ($this->invitations->findPendingByEmail($email) !== []) {
            return;
        }

        ($this->createTeam)(new CreateTeamCommand(
            Uuid::generate()->value(),
            $this->defaultTeamName,
            null,
            $userId->value()
        ));
    }

    private function isJoining(?string $inviteToken): bool
    {
        if ($inviteToken === null || $inviteToken === '') {
            return false;
        }

        $invitation = $this->invitations->findByToken($inviteToken);

        return $invitation !== null && $invitation->status()->isPending() && !$invitation->isExpired();
    }
}
