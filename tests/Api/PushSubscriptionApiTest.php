<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\HttpFoundation\Response;

class PushSubscriptionApiTest extends ApiTestCase
{
    public function testDeviceIsRegisteredForTheCaller(): void
    {
        $response = $this->postJson('/api/push/subscriptions', $this->device('alpha'));

        $registered = $this->assertJsonResponse($response, Response::HTTP_CREATED);
        $this->assertSame('https://push.example.com/alpha', $registered['endpoint']);
        $this->assertSame('Android', $registered['deviceLabel']);
        $this->assertNull($registered['lastUsedAt']);

        $subscriptions = $this->getJson('/api/push/subscriptions')['subscriptions'];
        $this->assertCount(1, $subscriptions);
        $this->assertSame($registered['id'], $subscriptions[0]['id']);
    }

    public function testRegisteringTheSameDeviceTwiceRefreshesItInsteadOfDuplicating(): void
    {
        $this->postJson('/api/push/subscriptions', $this->device('alpha'));

        $again = $this->assertJsonResponse(
            $this->postJson('/api/push/subscriptions', [
                'endpoint' => 'https://push.example.com/alpha',
                'publicKey' => 'refreshed-public-key',
                'authToken' => 'refreshed-auth-token',
                'deviceLabel' => 'iOS',
            ])
        );

        $this->assertSame('iOS', $again['deviceLabel']);
        $this->assertCount(1, $this->getJson('/api/push/subscriptions')['subscriptions']);
    }

    public function testDeviceMovesToWhoeverRegistersItLast(): void
    {
        $this->postJson('/api/push/subscriptions', $this->device('shared'));

        $this->loginAs($this->authenticate(Role::USER));
        $this->postJson('/api/push/subscriptions', $this->device('shared'));

        $this->assertCount(1, $this->getJson('/api/push/subscriptions')['subscriptions']);
    }

    public function testCallerStopsReceivingPushOnADevice(): void
    {
        $this->postJson('/api/push/subscriptions', $this->device('alpha'));

        $response = $this->deleteJson(
            '/api/push/subscriptions?endpoint=' . urlencode('https://push.example.com/alpha')
        );

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame([], $this->getJson('/api/push/subscriptions')['subscriptions']);
    }

    public function testSomebodyElsesDeviceCannotBeRemoved(): void
    {
        $this->postJson('/api/push/subscriptions', $this->device('alpha'));

        $this->loginAs($this->authenticate(Role::USER));

        $response = $this->deleteJson(
            '/api/push/subscriptions?endpoint=' . urlencode('https://push.example.com/alpha')
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testUnknownDeviceIsNotFound(): void
    {
        $response = $this->deleteJson(
            '/api/push/subscriptions?endpoint=' . urlencode('https://push.example.com/nothing')
        );

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testEndpointMustBeAUrl(): void
    {
        $response = $this->postJson('/api/push/subscriptions', [
            'endpoint' => 'not-a-url',
            'publicKey' => 'key',
            'authToken' => 'token',
            'deviceLabel' => null,
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testPublicKeyIsReportedAsUnavailableWhenPushIsNotConfigured(): void
    {
        $key = $this->getJson('/api/push/key');

        $this->assertArrayHasKey('available', $key);
        $this->assertArrayHasKey('publicKey', $key);
    }

    public function testTestPushNeedsADeviceFirst(): void
    {
        $response = $this->postJson('/api/push/test', []);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testTestPushReachesTheSendingPathOnceADeviceIsRegistered(): void
    {
        $this->postJson('/api/push/subscriptions', $this->device('alpha'));

        $response = $this->postJson('/api/push/test', []);

        $this->assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
    }

    /**
     * @return array<string, string|null>
     */
    private function device(string $name): array
    {
        return [
            'endpoint' => "https://push.example.com/{$name}",
            'publicKey' => 'a-public-key',
            'authToken' => 'an-auth-token',
            'deviceLabel' => 'Android',
        ];
    }
}
