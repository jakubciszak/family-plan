<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Shared\Mother\UuidMother;

class TaskAssignmentApiTest extends ApiTestCase
{
    public function testAssignTaskToUser(): void
    {
        $context = $this->createTeamAndAdmin();

        // Create a task
        $taskData = [
            'name' => 'Assign Task Test',
            'description' => 'Test assignment',
            'points' => 50,
            'frequency' => 'once',
            'teamId' => $context['teamId'],
            'createdBy' => $context['adminId'],
        ];

        $response = $this->postJson('/api/tasks', $taskData);
        $createdTask = $this->assertJsonResponse($response, 201);
        $taskId = $createdTask['id'];

        // Create a user
        $userData = [
            'name' => 'Test User',
            'email' => 'assignee@example.com',
            'password' => 'password123',
            'role' => 'ROLE_USER',
        ];

        $response = $this->postJson('/api/users', $userData);
        $createdUser = $this->assertJsonResponse($response, 201);
        $userId = $createdUser['id'];

        // Assign task to user
        $response = $this->postJson("/api/tasks/{$taskId}/assign", [
            'userId' => $userId,
        ]);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('assignedUserId', $data);
        $this->assertArrayHasKey('assignedUserName', $data);
        $this->assertSame($userId, $data['assignedUserId']);
        $this->assertSame('Test User', $data['assignedUserName']);
    }

    public function testAssignTaskReturns404ForNonexistentTask(): void
    {
        $nonexistentTaskId = UuidMother::random()->value();
        $userId = UuidMother::random()->value();
        $this->client->request('POST', "/api/tasks/{$nonexistentTaskId}/assign", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['userId' => $userId]));

        $response = $this->client->getResponse();
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testAssignTaskReturns404ForNonexistentUser(): void
    {
        $context = $this->createTeamAndAdmin();

        // Create a task
        $taskData = [
            'name' => 'Test Task',
            'description' => 'Test',
            'points' => 50,
            'frequency' => 'once',
            'teamId' => $context['teamId'],
            'createdBy' => $context['adminId'],
        ];

        $response = $this->postJson('/api/tasks', $taskData);
        $createdTask = $this->assertJsonResponse($response, 201);
        $taskId = $createdTask['id'];

        $nonexistentUserId = UuidMother::random()->value();
        $this->client->request('POST', "/api/tasks/{$taskId}/assign", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['userId' => $nonexistentUserId]));

        $response = $this->client->getResponse();
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testTaskListIncludesAssignmentInfo(): void
    {
        $context = $this->createTeamAndAdmin();

        // Create a task
        $taskData = [
            'name' => 'Task with Assignment',
            'description' => 'Test',
            'points' => 50,
            'frequency' => 'once',
            'teamId' => $context['teamId'],
            'createdBy' => $context['adminId'],
        ];

        $response = $this->postJson('/api/tasks', $taskData);
        $createdTask = $this->assertJsonResponse($response, 201);
        $taskId = $createdTask['id'];

        // Create a user
        $userData = [
            'name' => 'Assigned User',
            'email' => 'assigned@example.com',
            'password' => 'password123',
            'role' => 'ROLE_USER',
        ];

        $response = $this->postJson('/api/users', $userData);
        $createdUser = $this->assertJsonResponse($response, 201);
        $userId = $createdUser['id'];

        // Assign task
        $this->postJson("/api/tasks/{$taskId}/assign", ['userId' => $userId]);

        // Get task list
        $data = $this->getJson('/api/tasks');

        $this->assertIsArray($data);
        $this->assertArrayHasKey('tasks', $data);
        
        // Find the assigned task
        $assignedTask = null;
        foreach ($data['tasks'] as $task) {
            if ($task['id'] === $taskId) {
                $assignedTask = $task;
                break;
            }
        }

        $this->assertNotNull($assignedTask);
        $this->assertArrayHasKey('assignedUserId', $assignedTask);
        $this->assertArrayHasKey('assignedUserName', $assignedTask);
        $this->assertSame($userId, $assignedTask['assignedUserId']);
        $this->assertSame('Assigned User', $assignedTask['assignedUserName']);
    }
}
