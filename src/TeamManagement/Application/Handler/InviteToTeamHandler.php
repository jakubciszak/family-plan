<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Handler;

use App\Notifications\Application\Service\NotificationFacade;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Command\InviteToTeamCommand;
use App\TeamManagement\Application\Service\InvitationLinkGenerator;
use App\TeamManagement\Domain\Entity\TeamInvitation;
use App\TeamManagement\Domain\Exception\TeamNotFoundException;
use App\TeamManagement\Domain\Exception\UnauthorizedTeamActionException;
use App\TeamManagement\Domain\Repository\TeamInvitationRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class InviteToTeamHandler
{
    public function __construct(
        private readonly TeamRepositoryInterface $teamRepository,
        private readonly TeamMembershipRepositoryInterface $memberships,
        private readonly TeamInvitationRepositoryInterface $invitationRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly NotificationFacade $notificationFacade,
        private readonly InvitationLinkGenerator $invitationLink
    ) {
    }

    public function __invoke(InviteToTeamCommand $command): void
    {
        $teamId = Uuid::fromString($command->teamId);
        $invitedBy = Uuid::fromString($command->invitedBy);
        $email = Email::fromString($command->email);
        
        // Verify team exists
        $team = $this->teamRepository->findById($teamId);
        if ($team === null) {
            throw new TeamNotFoundException($command->teamId);
        }
        
        // Verify inviter is admin of the team
        if (!$this->memberships->isAdmin($invitedBy, $teamId)) {
            throw new UnauthorizedTeamActionException('Only team admins can invite members');
        }
        
        // Create invitation
        $invitation = TeamInvitation::create(
            Uuid::fromString($command->invitationId),
            $teamId,
            $email,
            TeamRole::fromString($command->role),
            $invitedBy
        );
        
        $this->invitationRepository->save($invitation);
        
        // Send notification
        $this->sendInvitationNotification($invitation, $team->name()->value());
    }

    private function sendInvitationNotification(TeamInvitation $invitation, string $teamName): void
    {
        $email = $invitation->email()->value();
        $user = $this->userRepository->findByEmail($invitation->email());
        
        // Generate invitation link
        $invitationUrl = $this->invitationLink->forToken($invitation->token());
        
        if ($user !== null) {
            // User exists - send invitation with link to accept
            $message = "You have been invited to join the team '{$teamName}'. Click the link to accept: {$invitationUrl}";
            $subject = "Team Invitation: {$teamName}";
        } else {
            // User doesn't exist - send invitation with registration link
            $message = "You have been invited to join the team '{$teamName}'. Please register first and then accept the invitation: {$invitationUrl}";
            $subject = "Team Invitation: {$teamName} - Registration Required";
        }
        
        $this->notificationFacade->sendEmail(
            recipient: $email,
            message: $message,
            subject: $subject,
            additionalParameters: [
                'teamName' => $teamName,
                'invitationToken' => $invitation->token(),
                'invitationUrl' => $invitationUrl
            ]
        );
    }
}
