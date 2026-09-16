<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\HttpFoundation\Response;

class PushAnnouncementApiTest extends ApiTestCase
{
    public function testAdminReachesEverybodyWithADevice(): void
    {
        $admin = $this->currentUser;
        $this->registerDevice('admin-device');

        $this->authenticate(Role::USER);
        $this->registerDevice('child-device');

        $this->authenticate(Role::USER);

        $this->loginAs($admin);
        $reachable = $this->getJson('/api/push/audience')['reachable'];
        $response = $this->postJson('/api/push/announcements', ['message' => 'Obiad za dziesięć minut']);

        $sent = $this->assertJsonResponse($response, Response::HTTP_ACCEPTED);
        $this->assertGreaterThanOrEqual(2, $reachable);
        $this->assertSame($reachable, $sent['recipients']);
    }

    public function testAdminReachesOnePerson(): void
    {
        $admin = $this->currentUser;

        $child = $this->authenticate(Role::USER);
        $this->registerDevice('child-device');

        $this->loginAs($admin);
        $response = $this->postJson('/api/push/announcements', [
            'message' => 'Twoja kolej na śmieci',
            'title' => 'Przypomnienie',
            'userId' => $child->id()->value(),
        ]);

        $this->assertSame(1, $this->assertJsonResponse($response, Response::HTTP_ACCEPTED)['recipients']);
    }

    public function testPersonWithoutADeviceIsAConflict(): void
    {
        $admin = $this->currentUser;
        $this->registerDevice('admin-device');

        $child = $this->authenticate(Role::USER);

        $this->loginAs($admin);
        $response = $this->postJson('/api/push/announcements', [
            'message' => 'Halo',
            'userId' => $child->id()->value(),
        ]);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testUnknownRecipientIsNotFound(): void
    {
        $this->registerDevice('admin-device');

        $response = $this->postJson('/api/push/announcements', [
            'message' => 'Halo',
            'userId' => '11111111-1111-4111-8111-111111111111',
        ]);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testEmptyMessageIsRejected(): void
    {
        $response = $this->postJson('/api/push/announcements', ['message' => '   ']);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testOnlyAnAdminMaySend(): void
    {
        $this->authenticate(Role::USER);

        $response = $this->postJson('/api/push/announcements', ['message' => 'Halo']);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testAudienceTellsWhoHasADevice(): void
    {
        $admin = $this->currentUser;
        $this->registerDevice('admin-device');

        $child = $this->authenticate(Role::USER);

        $this->loginAs($admin);
        $audience = $this->getJson('/api/push/audience');

        $devicesOf = array_column($audience['users'], 'devices', 'id');
        $this->assertSame(1, $devicesOf[$admin->id()->value()]);
        $this->assertSame(0, $devicesOf[$child->id()->value()]);
    }

    public function testAudienceIsForAdminsOnly(): void
    {
        $this->authenticate(Role::USER);

        $this->client->request('GET', '/api/push/audience', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    private function registerDevice(string $name): void
    {
        $this->postJson('/api/push/subscriptions', [
            'endpoint' => "https://push.example.com/{$name}",
            'publicKey' => 'a-public-key',
            'authToken' => 'an-auth-token',
            'deviceLabel' => 'Android',
        ]);
    }
}
