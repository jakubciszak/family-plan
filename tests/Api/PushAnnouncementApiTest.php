<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\HttpFoundation\Response;

class PushAnnouncementApiTest extends ApiTestCase
{
    public function testTeamAdminReachesEverybodyInTheirTeam(): void
    {
        $parent = $this->authenticate(Role::USER);
        $team = $this->teamAdministeredBy($parent);

        $child = $this->memberOf($team, 'Zosia');
        $otherChild = $this->memberOf($team, 'Janek');
        $this->giveDevice($child);
        $this->giveDevice($otherChild);

        $this->loginAs($parent);
        $response = $this->postJson('/api/push/announcements', ['message' => 'Obiad za dziesięć minut']);

        $this->assertSame(2, $this->assertJsonResponse($response, Response::HTTP_ACCEPTED)['recipients']);
    }

    public function testTeamAdminDoesNotSendToThemselves(): void
    {
        $parent = $this->authenticate(Role::USER);
        $team = $this->teamAdministeredBy($parent);
        $this->giveDevice($parent);

        $child = $this->memberOf($team, 'Zosia');
        $this->giveDevice($child);

        $this->loginAs($parent);
        $response = $this->postJson('/api/push/announcements', ['message' => 'Obiad']);

        $this->assertSame(1, $this->assertJsonResponse($response, Response::HTTP_ACCEPTED)['recipients']);
    }

    public function testTeamAdminDoesNotReachSomebodyFromAnotherTeam(): void
    {
        $parent = $this->authenticate(Role::USER);
        $this->teamAdministeredBy($parent);

        $stranger = $this->authenticate(Role::USER);
        $strangerTeam = $this->teamAdministeredBy($stranger);
        $strangerChild = $this->memberOf($strangerTeam, 'Obcy');
        $this->giveDevice($strangerChild);

        $this->loginAs($parent);
        $response = $this->postJson('/api/push/announcements', [
            'message' => 'Halo',
            'userId' => $strangerChild->id()->value(),
        ]);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testTeamAdminReachesOnePersonFromTheirTeam(): void
    {
        $parent = $this->authenticate(Role::USER);
        $team = $this->teamAdministeredBy($parent);

        $child = $this->memberOf($team, 'Zosia');
        $this->giveDevice($child);
        $this->memberOf($team, 'Janek');

        $this->loginAs($parent);
        $response = $this->postJson('/api/push/announcements', [
            'message' => 'Twoja kolej na śmieci',
            'title' => 'Przypomnienie',
            'userId' => $child->id()->value(),
        ]);

        $this->assertSame(1, $this->assertJsonResponse($response, Response::HTTP_ACCEPTED)['recipients']);
    }

    public function testPlainMemberCannotSend(): void
    {
        $parent = $this->authenticate(Role::USER);
        $team = $this->teamAdministeredBy($parent);
        $child = $this->memberOf($team, 'Zosia');

        $this->loginAs($child);
        $response = $this->postJson('/api/push/announcements', ['message' => 'Halo']);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testNobodyWithADeviceIsAConflict(): void
    {
        $parent = $this->authenticate(Role::USER);
        $team = $this->teamAdministeredBy($parent);
        $this->memberOf($team, 'Zosia');

        $this->loginAs($parent);
        $response = $this->postJson('/api/push/announcements', ['message' => 'Halo']);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testPersonWithoutADeviceIsAConflict(): void
    {
        $parent = $this->authenticate(Role::USER);
        $team = $this->teamAdministeredBy($parent);
        $child = $this->memberOf($team, 'Zosia');

        $this->loginAs($parent);
        $response = $this->postJson('/api/push/announcements', [
            'message' => 'Halo',
            'userId' => $child->id()->value(),
        ]);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testEmptyMessageIsRejected(): void
    {
        $parent = $this->authenticate(Role::USER);
        $this->teamAdministeredBy($parent);

        $response = $this->postJson('/api/push/announcements', ['message' => '   ']);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testAudienceIsTheTeamWithoutTheCaller(): void
    {
        $parent = $this->authenticate(Role::USER);
        $team = $this->teamAdministeredBy($parent);

        $child = $this->memberOf($team, 'Zosia');
        $this->giveDevice($child);
        $withoutDevice = $this->memberOf($team, 'Janek');

        $this->loginAs($parent);
        $audience = $this->getJson('/api/push/audience');

        $devicesOf = array_column($audience['users'], 'devices', 'id');
        $this->assertSame(1, $audience['reachable']);
        $this->assertSame([$child->id()->value(), $withoutDevice->id()->value()], array_keys($devicesOf));
        $this->assertSame(1, $devicesOf[$child->id()->value()]);
        $this->assertSame(0, $devicesOf[$withoutDevice->id()->value()]);
    }

    public function testAudienceIsClosedToPlainMembers(): void
    {
        $parent = $this->authenticate(Role::USER);
        $team = $this->teamAdministeredBy($parent);
        $child = $this->memberOf($team, 'Zosia');

        $this->loginAs($child);
        $this->client->request('GET', '/api/push/audience', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testSuperAdminReachesEverybody(): void
    {
        $superAdmin = $this->authenticate(Role::ADMIN);
        $stranger = $this->authenticate(Role::USER);
        $this->giveDevice($stranger);

        $this->loginAs($superAdmin);
        $reachable = $this->getJson('/api/push/audience')['reachable'];
        $response = $this->postJson('/api/push/announcements', ['message' => 'Ogłoszenie']);

        $sent = $this->assertJsonResponse($response, Response::HTTP_ACCEPTED);
        $this->assertGreaterThanOrEqual(1, $reachable);
        $this->assertSame($reachable, $sent['recipients']);
    }

    private function teamAdministeredBy(User $admin): Uuid
    {
        $this->loginAs($admin);
        $team = $this->assertJsonResponse(
            $this->postJson('/api/teams', ['name' => 'Rodzina', 'description' => null]),
            Response::HTTP_CREATED
        );

        return Uuid::fromString($team['id']);
    }

    private function memberOf(Uuid $teamId, string $name): User
    {
        $member = User::create(
            Uuid::generate(),
            $name,
            Email::fromString(sprintf('%s-%s@example.com', strtolower($name), uniqid())),
            password_hash('password123', PASSWORD_BCRYPT),
            Role::USER
        );

        static::getContainer()->get(UserRepositoryInterface::class)->save($member);
        static::getContainer()->get(TeamMembershipRepositoryInterface::class)->join(
            $teamId,
            $member->id(),
            TeamRole::member()
        );

        return $member;
    }

    private function giveDevice(User $user): void
    {
        $this->loginAs($user);
        $this->postJson('/api/push/subscriptions', [
            'endpoint' => sprintf('https://push.example.com/%s', uniqid()),
            'publicKey' => 'a-public-key',
            'authToken' => 'an-auth-token',
            'deviceLabel' => 'Android',
        ]);
    }
}
