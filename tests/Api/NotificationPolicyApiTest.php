<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\HttpFoundation\Response;

class NotificationPolicyApiTest extends ApiTestCase
{
    public function testMatrixListsEveryEventAndChannel(): void
    {
        $matrix = $this->getJson('/api/notification-policies');

        $this->assertSame(['email', 'sms', 'in_app', 'push'], $matrix['channels']);

        $events = array_column($matrix['events'], null, 'event');
        $this->assertArrayHasKey('task_completed', $events);
        $this->assertArrayHasKey('task_approved', $events);
        $this->assertArrayHasKey('user_welcome', $events);
        $this->assertArrayHasKey('account_activation', $events);
    }

    public function testEventWithoutAPolicyIsReportedAsEmail(): void
    {
        $this->assertSame(['email'], $this->channelsOf('user_welcome'));
    }

    public function testAdminMovesAnEventToInApp(): void
    {
        $response = $this->putJson('/api/notification-policies/task_approved', [
            'channels' => ['in_app'],
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $this->assertSame(['in_app'], $this->channelsOf('task_approved'));
    }

    public function testAdminSwitchesAnEventOffCompletely(): void
    {
        $this->putJson('/api/notification-policies/task_completed', ['channels' => []]);

        $this->assertSame([], $this->channelsOf('task_completed'));
    }

    public function testAccountActivationCannotBeSwitchedOff(): void
    {
        $response = $this->putJson('/api/notification-policies/account_activation', ['channels' => []]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(['email'], $this->channelsOf('account_activation'));
    }

    public function testUnknownEventIsRejected(): void
    {
        $response = $this->putJson('/api/notification-policies/task_forgotten', ['channels' => ['email']]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testUnknownChannelIsRejected(): void
    {
        $response = $this->putJson('/api/notification-policies/task_approved', ['channels' => ['carrier_pigeon']]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testChannelsMustBeAnArray(): void
    {
        $response = $this->putJson('/api/notification-policies/task_approved', ['channels' => 'email']);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testOrdinaryUserCannotReadTheMatrix(): void
    {
        $this->loginAs($this->authenticate(Role::USER));

        $this->client->request('GET', '/api/notification-policies', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testOrdinaryUserCannotChangeTheMatrix(): void
    {
        $this->loginAs($this->authenticate(Role::USER));

        $response = $this->putJson('/api/notification-policies/task_approved', ['channels' => []]);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * @return list<string>
     */
    private function channelsOf(string $event): array
    {
        $events = array_column($this->getJson('/api/notification-policies')['events'], null, 'event');

        return $events[$event]['channels'];
    }
}
