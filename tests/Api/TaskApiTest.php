<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Shared\Mother\UuidMother;

class TaskApiTest extends ApiTestCase
{
    private function createTeamAndUser(): array
    {
        // Create admin user
        $adminData = [
            'name' => 'Admin User',
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => 'adminpass123',
            'role' => 'ROLE_ADMIN',
        ];
        $adminResponse = $this->postJson('/api/users', $adminData);
        $admin = $this->assertJsonResponse($adminResponse, 201);

        // Create team
        $teamData = [
            'name' => 'Test Team',
            'description' => 'Test Description',
            'createdBy' => $admin['id'],
        ];
        $teamResponse = $this->postJson('/api/teams', $teamData);
        $team = $this->assertJsonResponse($teamResponse, 201);

        return [
            'adminId' => $admin['id'],
            'teamId' => $team['id'],
        ];
    }

    public function testListTasksReturnsEmptyArray(): void
    {
        $data = $this->getJson('/api/tasks');

        $this->assertIsArray($data);
        $this->assertArrayHasKey('tasks', $data);
    }

    public function testCreateTask(): void
    {
        $context = $this->createTeamAndUser();

        $taskData = [
            'name' => 'Test Task',
            'description' => 'Test Description',
            'points' => 100,
            'frequency' => 'daily',
            'teamId' => $context['teamId'],
            'createdBy' => $context['adminId'],
        ];

        $response = $this->postJson('/api/tasks', $taskData);
        $data = $this->assertJsonResponse($response, 201);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertSame('Test Task', $data['name']);
        $this->assertSame('Test Description', $data['description']);
        $this->assertSame(100, $data['points']);
        $this->assertSame('daily', $data['frequency']);
    }

    public function testGetTaskById(): void
    {
        $context = $this->createTeamAndUser();

        // Create a task first
        $taskData = [
            'name' => 'Get Task Test',
            'description' => 'Description',
            'points' => 50,
            'frequency' => 'weekly',
            'teamId' => $context['teamId'],
            'createdBy' => $context['adminId'],
        ];

        $response = $this->postJson('/api/tasks', $taskData);
        $createdTask = $this->assertJsonResponse($response, 201);
        $taskId = $createdTask['id'];

        // Get the task by ID
        $data = $this->getJson("/api/tasks/{$taskId}");

        $this->assertSame($taskId, $data['id']);
        $this->assertSame('Get Task Test', $data['name']);
    }

    public function testCompleteTask(): void
    {
        $context = $this->createTeamAndUser();

        // Create a task first
        $taskData = [
            'name' => 'Complete Task Test',
            'description' => 'Description',
            'points' => 75,
            'frequency' => 'once',
            'teamId' => $context['teamId'],
            'createdBy' => $context['adminId'],
        ];

        $response = $this->postJson('/api/tasks', $taskData);
        $createdTask = $this->assertJsonResponse($response, 201);
        $taskId = $createdTask['id'];

        // Complete the task
        $response = $this->postJson("/api/tasks/{$taskId}/complete", [
            'userId' => UuidMother::random()->value(),
        ]);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('status', $data);
        $this->assertSame('completed', $data['status']);
    }

    public function testApproveTask(): void
    {
        $context = $this->createTeamAndUser();

        // Create a regular user to assign the task to
        $userData = [
            'name' => 'Task User',
            'email' => 'taskuser_' . uniqid() . '@example.com',
            'password' => 'userpass123',
            'role' => 'ROLE_USER',
        ];
        $userResponse = $this->postJson('/api/users', $userData);
        $user = $this->assertJsonResponse($userResponse, 201);
        $userId = $user['id'];

        // Create a task
        $taskData = [
            'name' => 'Approve Task Test',
            'description' => 'Description',
            'points' => 60,
            'frequency' => 'monthly',
            'teamId' => $context['teamId'],
            'createdBy' => $context['adminId'],
        ];

        $response = $this->postJson('/api/tasks', $taskData);
        $createdTask = $this->assertJsonResponse($response, 201);
        $taskId = $createdTask['id'];

        // Assign the task to the user
        $this->postJson("/api/tasks/{$taskId}/assign", [
            'userId' => $userId,
        ]);

        // Complete the task
        $this->postJson("/api/tasks/{$taskId}/complete", [
            'userId' => $userId,
        ]);

        // Approve the task with admin user
        $response = $this->postJson("/api/tasks/{$taskId}/approve", [
            'adminId' => $context['adminId'],
        ]);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('status', $data);
        $this->assertSame('approved', $data['status']);
    }
}
