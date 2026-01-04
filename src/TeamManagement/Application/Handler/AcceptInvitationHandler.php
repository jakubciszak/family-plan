<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Command\AcceptInvitationCommand;
use App\TeamManagement\Domain\Entity\TeamMember;
use App\TeamManagement\Domain\Exception\InvitationNotFoundException;
use App\TeamManagement\Domain\Repository\TeamInvitationRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamMemberRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AcceptInvitationHandler
{
    public function __construct(
        private readonly TeamInvitationRepositoryInterface $invitationRepository,
        private readonly TeamMemberRepositoryInterface $teamMemberRepository
    ) {
    }

    public function __invoke(AcceptInvitationCommand $command): void
    {
        $invitation = $this->invitationRepository->findByToken($command->token);
        
        if ($invitation === null) {
            throw new InvitationNotFoundException($command->token);
        }
        
        // Accept invitation (this validates it's pending and not expired)
        $invitation->accept();
        $this->invitationRepository->save($invitation);
        
        // Add user as team member
        $userId = Uuid::fromString($command->userId);
        $member = TeamMember::create(
            Uuid::generate(),
            $invitation->teamId(),
            $userId,
            $invitation->role()
        );
        
        $this->teamMemberRepository->save($member);
    }
}
