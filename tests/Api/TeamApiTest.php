<?php

declare(strict_types=1);

namespace App\Tests\Api;

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
}
