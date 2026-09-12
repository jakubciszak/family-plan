<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\HttpFoundation\Response;

class TaskTypeApiTest extends ApiTestCase
{
    public function testTeamAdminDefinesATaskType(): void
    {
        $teamId = $this->createTeam();

        $response = $this->postJson('/api/task-templates', [
            'teamId' => $teamId,
            'name' => 'Odkurzyc salon',
            'description' => 'Caly parter',
            'points' => 30,
            'frequency' => 'daily',
            'executionLimit' => ['type' => 'per_day', 'count' => 3],
        ]);

        $created = $this->assertJsonResponse($response, Response::HTTP_CREATED);
        $this->assertSame('Odkurzyc salon', $created['name']);
        $this->assertSame(['type' => 'per_day', 'count' => 3], $created['executionLimit']);
        $this->assertSame(3, $created['remaining']);
    }

    public function testUnlimitedTypeReportsNoRemainingCap(): void
    {
        $teamId = $this->createTeam();

        $created = $this->assertJsonResponse(
            $this->postJson('/api/task-templates', [
                'teamId' => $teamId,
                'name' => 'Pomoc przy zmywaniu',
                'description' => 'Kiedy tylko chcesz',
                'points' => 5,
                'frequency' => 'daily',
                'executionLimit' => ['type' => 'unlimited'],
            ]),
            Response::HTTP_CREATED
        );

        $this->assertNull($created['remaining']);
    }

    public function testTypesAreScopedToTheCallersTeams(): void
    {
        $teamId = $this->createTeam('Obca rodzina');
        $this->postJson('/api/task-templates', [
            'teamId' => $teamId,
            'name' => 'Cudze zadanie',
            'description' => 'opis',
            'points' => 10,
            'frequency' => 'once',
            'executionLimit' => ['type' => 'once'],
        ]);

        $this->loginAs($this->authenticate(Role::USER));

        $names = array_column($this->getJson('/api/task-templates')['templates'], 'name');
        $this->assertNotContains('Cudze zadanie', $names);
    }

    public function testTypeCannotBeDefinedForSomebodyElsesTeam(): void
    {
        $foreignTeam = $this->createTeam('Obca rodzina');

        $this->loginAs($this->authenticate(Role::USER));

        $response = $this->postJson('/api/task-templates', [
            'teamId' => $foreignTeam,
            'name' => 'Podszywka',
            'description' => 'opis',
            'points' => 10,
            'frequency' => 'once',
            'executionLimit' => ['type' => 'once'],
        ]);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testTypeCanBeDeactivatedAndActivatedAgain(): void
    {
        $teamId = $this->createTeam();
        $created = $this->assertJsonResponse(
            $this->postJson('/api/task-templates', [
                'teamId' => $teamId,
                'name' => 'Sezonowe',
                'description' => 'opis',
                'points' => 10,
                'frequency' => 'once',
                'executionLimit' => ['type' => 'once'],
            ]),
            Response::HTTP_CREATED
        );

        $this->assertSame(
            Response::HTTP_OK,
            $this->postJson("/api/task-templates/{$created['id']}/deactivate", [])->getStatusCode()
        );

        $templates = $this->getJson('/api/task-templates')['templates'];
        $this->assertFalse($templates[0]['isActive']);

        $this->postJson("/api/task-templates/{$created['id']}/activate", []);
        $this->assertTrue($this->getJson('/api/task-templates')['templates'][0]['isActive']);
    }

    public function testInvalidLimitIsRejected(): void
    {
        $teamId = $this->createTeam();

        $response = $this->postJson('/api/task-templates', [
            'teamId' => $teamId,
            'name' => 'Zly limit',
            'description' => 'opis',
            'points' => 10,
            'frequency' => 'once',
            'executionLimit' => ['type' => 'per_day', 'count' => 0],
        ]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    private function createTeam(string $name = 'Rodzina'): string
    {
        $team = $this->assertJsonResponse(
            $this->postJson('/api/teams', ['name' => $name, 'description' => null]),
            Response::HTTP_CREATED
        );

        return $team['id'];
    }
}
