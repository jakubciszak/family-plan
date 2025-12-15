<?php

declare(strict_types=1);

namespace App\Tests\Api;

class UserApiTest extends ApiTestCase
{
    public function testListUsersReturnsArray(): void
    {
        $data = $this->getJson('/api/users');

        $this->assertIsArray($data);
        $this->assertArrayHasKey('users', $data);
    }

    public function testCreateUser(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password123',
            'role' => 'ROLE_USER',
        ];

        $response = $this->postJson('/api/users', $userData);
        $data = $this->assertJsonResponse($response, 201);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertSame('Test User', $data['name']);
        $this->assertSame('testuser@example.com', $data['email']);
        $this->assertSame('ROLE_USER', $data['role']);
        $this->assertArrayNotHasKey('password', $data); // Password should not be returned
    }

    public function testGetUserById(): void
    {
        // Create a user first
        $userData = [
            'name' => 'Get User Test',
            'email' => 'getuser@example.com',
            'password' => 'password123',
            'role' => 'ROLE_ADMIN',
        ];

        $response = $this->postJson('/api/users', $userData);
        $createdUser = $this->assertJsonResponse($response, 201);
        $userId = $createdUser['id'];

        // Get the user by ID
        $data = $this->getJson("/api/users/{$userId}");

        $this->assertSame($userId, $data['id']);
        $this->assertSame('Get User Test', $data['name']);
        $this->assertSame('getuser@example.com', $data['email']);
    }
}
