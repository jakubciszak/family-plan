<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Shared\Mother\UuidMother;

class UserPointsApiTest extends ApiTestCase
{
    public function testGetUserPointsReturnsZeroForNewUser(): void
    {
        // Create a user first
        $userData = [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password123',
            'role' => 'ROLE_USER',
        ];

        $response = $this->postJson('/api/users', $userData);
        $createdUser = $this->assertJsonResponse($response, 201);
        $userId = $createdUser['id'];

        // Get user points
        $data = $this->getJson("/api/users/{$userId}/points");

        $this->assertArrayHasKey('userId', $data);
        $this->assertArrayHasKey('balance', $data);
        $this->assertSame($userId, $data['userId']);
        $this->assertSame(0, $data['balance']);
    }

    public function testGetUserPointsReturns404ForNonexistentUser(): void
    {
        $nonexistentUserId = UuidMother::random()->value();
        
        $this->client->request('GET', "/api/users/{$nonexistentUserId}/points", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $this->client->getResponse();
        $this->assertSame(404, $response->getStatusCode());
    }
}
