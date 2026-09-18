<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\UserManagement\Domain\ValueObject\Role;

final class ActionPlanApiTest extends ApiTestCase
{
    private function payload(): array
    {
        return [
            'name' => 'Room',
            'steps' => [['name' => 'Bed', 'stages' => []], ['name' => 'Wardrobe', 'stages' => ['LEGO', 'Shelves']]],
            'estimatedMinutes' => 30,
            'reminderMinutes' => 10,
        ];
    }

    public function testRegularUserCanSaveReviseReloadAndDeleteAPlan(): void
    {
        $this->loginAs($this->authenticate(Role::USER));
        $plan = $this->assertJsonResponse($this->postJson('/api/action-plans', $this->payload()), 201);
        self::assertSame($this->payload()['steps'], $plan['steps']);
        self::assertSame([$plan], $this->getJson('/api/action-plans')['plans']);

        $revised = $this->payload();
        $revised['name'] = 'Room tomorrow';
        $revised['estimatedMinutes'] = null;
        $revised['reminderMinutes'] = 5;
        $revised['steps'] = array_reverse($revised['steps']);
        $saved = $this->assertJsonResponse($this->putJson('/api/action-plans/'.$plan['id'], $revised));
        self::assertSame('Room tomorrow', $saved['name']);
        self::assertNull($saved['estimatedMinutes']);
        self::assertSame(5, $saved['reminderMinutes']);
        self::assertSame($revised['steps'], $saved['steps']);
        self::assertSame([$saved], $this->getJson('/api/action-plans')['plans']);
        self::assertSame(204, $this->deleteJson('/api/action-plans/'.$plan['id'])->getStatusCode());
        self::assertSame([], $this->getJson('/api/action-plans')['plans']);
    }

    public function testOtherUsersIncludingAdminsCannotListChangeOrDeleteMyPlan(): void
    {
        $this->loginAs($this->authenticate(Role::USER));
        $owner = $this->currentUser;
        $plan = $this->assertJsonResponse($this->postJson('/api/action-plans', $this->payload()), 201);
        foreach ([Role::USER, Role::ADMIN] as $role) {
            $this->loginAs($this->authenticate($role));
            self::assertSame([], $this->getJson('/api/action-plans')['plans']);
            self::assertSame(404, $this->putJson('/api/action-plans/'.$plan['id'], $this->payload())->getStatusCode());
            self::assertSame(404, $this->deleteJson('/api/action-plans/'.$plan['id'])->getStatusCode());
        }
        $this->loginAs($owner);
        self::assertSame([$plan], $this->getJson('/api/action-plans')['plans']);
    }

    public function testMalformedStepsAndDurationsAreRejected(): void
    {
        foreach ([['steps' => []], ['estimatedMinutes' => 0], ['reminderMinutes' => 0], ['name' => '']] as $invalid) {
            self::assertSame(422, $this->postJson('/api/action-plans', array_replace($this->payload(), $invalid))->getStatusCode());
        }
        foreach ([['name' => 'Bed', 'stages' => [' ']], ['name' => 'Bed', 'stages' => [['bad' => true]]], 'not an object'] as $invalidStep) {
            self::assertSame(400, $this->postJson('/api/action-plans', array_replace($this->payload(), ['steps' => [$invalidStep]]))->getStatusCode());
        }
        self::assertSame([], $this->getJson('/api/action-plans')['plans']);
    }

    public function testInvalidIdentifiersReturnNotFound(): void
    {
        self::assertSame(404, $this->putJson('/api/action-plans/not-a-uuid', $this->payload())->getStatusCode());
        self::assertSame(404, $this->deleteJson('/api/action-plans/not-a-uuid')->getStatusCode());
    }

    public function testDefaultsToTenMinuteRemindersWithoutAnEstimate(): void
    {
        $payload = $this->payload();
        unset($payload['estimatedMinutes'], $payload['reminderMinutes']);
        $plan = $this->assertJsonResponse($this->postJson('/api/action-plans', $payload), 201);
        self::assertNull($plan['estimatedMinutes']);
        self::assertSame(10, $plan['reminderMinutes']);
        self::assertSame('soft', $plan['reminderSound']);
    }

    public function testSavesReminderSoundAndPreservesItWhenOlderClientsOmitTheField(): void
    {
        $plan = $this->assertJsonResponse($this->postJson('/api/action-plans', [...$this->payload(), 'reminderSound' => 'melody']), 201);
        self::assertSame('melody', $plan['reminderSound']);
        self::assertSame('melody', $this->getJson('/api/action-plans')['plans'][0]['reminderSound']);
        $updated = $this->assertJsonResponse($this->putJson('/api/action-plans/'.$plan['id'], $this->payload()));
        self::assertSame('melody', $updated['reminderSound']);
        foreach (['soft', 'bell', 'double'] as $sound) {
            $updated = $this->assertJsonResponse($this->putJson('/api/action-plans/'.$plan['id'], [...$this->payload(), 'reminderSound' => $sound]));
            self::assertSame($sound, $updated['reminderSound']);
        }
        self::assertSame(422, $this->putJson('/api/action-plans/'.$plan['id'], [...$this->payload(), 'reminderSound' => 'unknown'])->getStatusCode());
        self::assertSame('double', $this->getJson('/api/action-plans')['plans'][0]['reminderSound']);
    }

    public function testAnonymousRequestsAreRejected(): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/api/action-plans', [], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertSame(401, $this->postJson('/api/action-plans', $this->payload())->getStatusCode());
    }
}
