<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\HttpFoundation\Response;

class TeamApiTest extends ApiTestCase
{
    public function testTeamListingCarriesTheCallersRole(): void
    {
        $response = $this->postJson('/api/teams', [
            'name' => 'Rodzina',
            'description' => 'nasz dom',
        ]);
        $this->assertJsonResponse($response, Response::HTTP_CREATED);

        $teams = $this->getJson('/api/teams')['teams'];

        $this->assertNotEmpty($teams);
        $this->assertArrayHasKey(
            'role',
            $teams[0],
            'The task list hides its create button unless the team carries the caller role.'
        );
        $this->assertSame('admin', $teams[0]['role']);
    }

    public function testMembersListCarriesPendingInvitationsWithACopyableLink(): void
    {
        $team = $this->assertJsonResponse(
            $this->postJson('/api/teams', ['name' => 'Rodzina', 'description' => null]),
            Response::HTTP_CREATED
        );

        $this->assertJsonResponse(
            $this->postJson("/api/teams/{$team['id']}/invite", [
                'email' => 'bezkonta@example.com',
                'role' => 'member',
            ]),
            Response::HTTP_CREATED
        );

        $payload = $this->getJson("/api/teams/{$team['id']}/members");

        $this->assertArrayHasKey('invitations', $payload);
        $this->assertCount(1, $payload['invitations']);

        $invitation = $payload['invitations'][0];
        $this->assertSame('bezkonta@example.com', $invitation['email']);
        $this->assertSame('pending', $invitation['status']);
        $this->assertFalse($invitation['accountExists']);
        $this->assertStringContainsString('?invite=' . $invitation['token'], $invitation['invitationUrl']);
    }

    public function testInvitationCannotBeAcceptedByADifferentAccount(): void
    {
        $team = $this->assertJsonResponse(
            $this->postJson('/api/teams', ['name' => 'Rodzina', 'description' => null]),
            Response::HTTP_CREATED
        );

        $invite = $this->assertJsonResponse(
            $this->postJson("/api/teams/{$team['id']}/invite", [
                'email' => 'ktos-inny@example.com',
                'role' => 'member',
            ]),
            Response::HTTP_CREATED
        );

        $token = $invite['invitation']['token'];

        $this->assertSame(
            Response::HTTP_FORBIDDEN,
            $this->postJson("/api/teams/invitations/{$token}/accept", [])->getStatusCode()
        );

        $teams = $this->getJson('/api/teams')['teams'];
        $this->assertCount(1, $teams);
        $this->assertSame('admin', $teams[0]['role']);

        $this->assertCount(1, $this->getJson("/api/teams/{$team['id']}/members")['members']);
    }

    public function testInvitationTokensAreHiddenFromPlainMembers(): void
    {
        $team = $this->assertJsonResponse(
            $this->postJson('/api/teams', ['name' => 'Rodzina', 'description' => null]),
            Response::HTTP_CREATED
        );

        $this->assertJsonResponse(
            $this->postJson("/api/teams/{$team['id']}/invite", [
                'email' => 'bezkonta@example.com',
                'role' => 'member',
            ]),
            Response::HTTP_CREATED
        );

        $this->loginAs($this->authenticate(Role::USER));

        $this->assertArrayNotHasKey('invitations', $this->getJson("/api/teams/{$team['id']}/members"));
    }
}
