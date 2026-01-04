<?php

declare(strict_types=1);

namespace App\Tests\TeamManagement\Domain;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\TeamInvitation;
use App\TeamManagement\Domain\ValueObject\InvitationStatus;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

class TeamInvitationTest extends TestCase
{
    public function testCanCreateInvitation(): void
    {
        $id = Uuid::generate();
        $teamId = Uuid::generate();
        $email = Email::fromString('test@example.com');
        $role = TeamRole::member();
        $invitedBy = Uuid::generate();

        $invitation = TeamInvitation::create($id, $teamId, $email, $role, $invitedBy);

        $this->assertEquals($id, $invitation->id());
        $this->assertEquals($teamId, $invitation->teamId());
        $this->assertEquals($email, $invitation->email());
        $this->assertEquals($role, $invitation->role());
        $this->assertEquals($invitedBy, $invitation->invitedBy());
        $this->assertTrue($invitation->status()->isPending());
        $this->assertNotNull($invitation->token());
        $this->assertNotNull($invitation->expiresAt());
    }

    public function testCanAcceptInvitation(): void
    {
        $invitation = TeamInvitation::create(
            Uuid::generate(),
            Uuid::generate(),
            Email::fromString('test@example.com'),
            TeamRole::member(),
            Uuid::generate()
        );

        $invitation->accept();

        $this->assertTrue($invitation->status()->isAccepted());
        $this->assertNotNull($invitation->respondedAt());
    }

    public function testCanRejectInvitation(): void
    {
        $invitation = TeamInvitation::create(
            Uuid::generate(),
            Uuid::generate(),
            Email::fromString('test@example.com'),
            TeamRole::member(),
            Uuid::generate()
        );

        $invitation->reject();

        $this->assertTrue($invitation->status()->isRejected());
        $this->assertNotNull($invitation->respondedAt());
    }

    public function testCannotAcceptNonPendingInvitation(): void
    {
        $invitation = TeamInvitation::create(
            Uuid::generate(),
            Uuid::generate(),
            Email::fromString('test@example.com'),
            TeamRole::member(),
            Uuid::generate()
        );

        $invitation->accept();

        $this->expectException(\DomainException::class);
        $invitation->accept(); // Try to accept again
    }
}
