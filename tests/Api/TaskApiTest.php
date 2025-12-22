<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Shared\Mother\UuidMother;

class TaskApiTest extends ApiTestCase
{
    public function testListTasksReturnsEmptyArray(): void
    {
        $data = $this->getJson('/api/tasks');

        $this->assertIsArray($data);
        $this->assertArrayHasKey('tasks', $data);
    }

    public function testCreateTask(): void
    {
        $taskData = [
            'name' => 'Test Task',
            'description' => 'Test Description',
            'points' => 100,
            'frequency' => 'daily',
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
        // Create a task first
        $taskData = [
            'name' => 'Get Task Test',
            'description' => 'Description',
            'points' => 50,
            'frequency' => 'weekly',
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
        // Create a task first
        $taskData = [
            'name' => 'Complete Task Test',
            'description' => 'Description',
            'points' => 75,
            'frequency' => 'once',
        ];

        $response = $this->postJson('/api/tasks', $taskData);
        $createdTask = $this->assertJsonResponse($response, 201);
        $taskId = $createdTask['id'];

        // Complete the task
        $response = $this->postJson("/api/tasks/{$taskId}/complete", [
            'userId' => UuidMother::fixed()->value(),
        ]);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('status', $data);
        $this->assertSame('completed', $data['status']);
    }

    public function testApproveTask(): void
    {
        // Create and complete a task first
        $taskData = [
            'name' => 'Approve Task Test',
            'description' => 'Description',
            'points' => 60,
            'frequency' => 'monthly',
        ];

        $response = $this->postJson('/api/tasks', $taskData);
        $createdTask = $this->assertJsonResponse($response, 201);
        $taskId = $createdTask['id'];

        // Complete the task
        $this->postJson("/api/tasks/{$taskId}/complete", [
            'userId' => UuidMother::fixed()->value(),
        ]);

        // Approve the task
        $response = $this->postJson("/api/tasks/{$taskId}/approve", [
            'adminId' => UuidMother::fixed()->value(),
        ]);
        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('status', $data);
        $this->assertSame('approved', $data['status']);
    }
}
