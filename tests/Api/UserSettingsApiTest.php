<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\HttpFoundation\Response;

class UserSettingsApiTest extends ApiTestCase
{
    private const CHANNELS = [
        'preference_type' => 'notifications',
        'options' => [
            ['name' => 'email', 'enabled' => false],
            ['name' => 'sms', 'enabled' => false],
            ['name' => 'in_app', 'enabled' => true],
            ['name' => 'push', 'enabled' => true],
        ],
    ];

    public function testEverybodyManagesTheirOwnSettings(): void
    {
        $me = $this->authenticate(Role::USER);

        $this->assertJsonResponse($this->putJson('/api/user-settings/' . $me->id()->value(), self::CHANNELS));

        $preferences = $this->getJson('/api/user-settings/' . $me->id()->value())['preferences'];
        $this->assertFalse(array_column($preferences[0]['options'], 'enabled', 'name')['email']);
    }

    public function testNobodyReadsOrChangesSomebodyElsesSettings(): void
    {
        $other = $this->authenticate(Role::USER);
        $this->authenticate(Role::USER);

        $this->client->request('GET', '/api/user-settings/' . $other->id()->value(), [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->putJson('/api/user-settings/' . $other->id()->value(), self::CHANNELS)->getStatusCode());
    }

    public function testTheApplicationAdministratorMayManageAnyone(): void
    {
        $other = $this->authenticate(Role::USER);
        $this->authenticate(Role::ADMIN);

        $this->assertJsonResponse($this->putJson('/api/user-settings/' . $other->id()->value(), self::CHANNELS));
    }
}
