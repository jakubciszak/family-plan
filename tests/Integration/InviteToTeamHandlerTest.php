<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Command\InviteToTeamCommand;
use App\TeamManagement\Application\Handler\InviteToTeamHandler;
use App\TeamManagement\Application\Service\InvitationLinkGenerator;
use App\TeamManagement\Domain\Exception\UnauthorizedTeamActionException;
use App\TeamManagement\Domain\Repository\TeamInvitationRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;

class InviteToTeamHandlerTest extends IntegrationTestCase
{
    private InMemoryNotificationAdapter $sentMail;

    private InviteToTeamHandler $handler;

    private TeamInvitationRepositoryInterface $invitations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sentMail = new InMemoryNotificationAdapter();
        $this->invitations = $this->service(TeamInvitationRepositoryInterface::class);

        $this->handler = new InviteToTeamHandler(
            $this->service(TeamRepositoryInterface::class),
            $this->service(TeamMembershipRepositoryInterface::class),
            $this->invitations,
            $this->service(UserRepositoryInterface::class),
            new NotificationFacade([$this->sentMail]),
            new InvitationLinkGenerator('https://family-plan.example.com/')
        );
    }

    public function testInvitationLinkPointsAtTheApplicationWithTheToken(): void
    {
        $founder = $this->user('Rodzic');
        $teamId = $this->team($founder);
        $invitedEmail = sprintf('zaproszony-%s@example.com', uniqid());

        ($this->handler)(new InviteToTeamCommand(
            Uuid::generate()->value(),
            $teamId->value(),
            $invitedEmail,
            'member',
            $founder->id()->value()
        ));

        $pending = $this->invitations->findPendingByEmail(Email::fromString($invitedEmail));
        $this->assertCount(1, $pending);

        $sent = $this->sentMail->getSentNotifications();
        $this->assertCount(1, $sent);
        $this->assertSame($invitedEmail, $sent[0]['recipient']);
        $this->assertStringContainsString(
            sprintf('https://family-plan.example.com/?invite=%s', $pending[0]->token()),
            $sent[0]['message']
        );
    }

    public function testTheInvitationIsStoredForTheTeamThatIssuedIt(): void
    {
        $founder = $this->user('Rodzic');
        $teamId = $this->team($founder);
        $invitedEmail = sprintf('zaproszony-%s@example.com', uniqid());

        ($this->handler)(new InviteToTeamCommand(
            Uuid::generate()->value(),
            $teamId->value(),
            $invitedEmail,
            'member',
            $founder->id()->value()
        ));

        $invitation = $this->invitations->findPendingByEmail(Email::fromString($invitedEmail))[0];

        $this->assertTrue($invitation->teamId()->equals($teamId));
        $this->assertSame(TeamRole::member()->value(), $invitation->role()->value());
    }

    public function testOnlyATeamAdminInvites(): void
    {
        $founder = $this->user('Rodzic');
        $teamId = $this->team($founder);
        $child = $this->user('Dziecko');
        $this->join($teamId, $child, TeamRole::member());

        $this->expectException(UnauthorizedTeamActionException::class);

        ($this->handler)(new InviteToTeamCommand(
            Uuid::generate()->value(),
            $teamId->value(),
            sprintf('ktos-%s@example.com', uniqid()),
            'member',
            $child->id()->value()
        ));
    }
}
