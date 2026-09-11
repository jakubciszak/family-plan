<?php

declare(strict_types=1);

namespace App\Tests\TeamManagement\Application;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Domain\Port\NotificationPortInterface;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Command\InviteToTeamCommand;
use App\TeamManagement\Application\Handler\InviteToTeamHandler;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\Entity\TeamInvitation;
use App\TeamManagement\Domain\Repository\TeamInvitationRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamMemberRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamName;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

class InviteToTeamHandlerTest extends TestCase
{
    public function testInvitationLinkPointsAtTheApplicationWithTheToken(): void
    {
        $recorder = new class implements NotificationPortInterface {
            public ?NotificationMessage $message = null;

            public function send(Recipient $recipient, NotificationMessage $message, NotificationChannel $channel): void
            {
                $this->message = $message;
            }

            public function supports(NotificationChannel $channel): bool
            {
                return true;
            }
        };

        $teamId = Uuid::generate();
        $invitedBy = Uuid::generate();
        $invitationId = Uuid::generate();

        $teamRepository = $this->createMock(TeamRepositoryInterface::class);
        $teamRepository->method('findById')->willReturn(
            Team::create($teamId, TeamName::fromString('Rodzina'), null, $invitedBy)
        );

        $memberRepository = $this->createMock(TeamMemberRepositoryInterface::class);
        $memberRepository->method('isUserAdminOfTeam')->willReturn(true);

        $savedInvitation = null;
        $invitationRepository = $this->createMock(TeamInvitationRepositoryInterface::class);
        $invitationRepository->method('save')->willReturnCallback(
            function (TeamInvitation $invitation) use (&$savedInvitation): void {
                $savedInvitation = $invitation;
            }
        );

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findByEmail')->willReturn(null);

        $handler = new InviteToTeamHandler(
            $teamRepository,
            $memberRepository,
            $invitationRepository,
            $userRepository,
            new NotificationFacade([$recorder]),
            'https://family-plan.example.com/'
        );

        $handler(new InviteToTeamCommand(
            $invitationId->value(),
            $teamId->value(),
            'zaproszony@example.com',
            'member',
            $invitedBy->value()
        ));

        $this->assertNotNull($savedInvitation);
        $this->assertNotNull($recorder->message);
        $this->assertStringContainsString(
            sprintf('https://family-plan.example.com/?invite=%s', $savedInvitation->token()),
            $recorder->message->content()
        );
    }
}
