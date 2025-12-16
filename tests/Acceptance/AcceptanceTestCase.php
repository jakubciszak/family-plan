<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for acceptance tests using behavior-driven language
 * Provides methods that read like natural language specifications
 */
abstract class AcceptanceTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected ?array $lastResponse = null;
    protected ?string $lastCreatedResourceId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->lastResponse = null;
        $this->lastCreatedResourceId = null;
    }

    // === Given (Setup) Methods ===

    protected function givenIAmAnAuthenticatedUser(): void
    {
        // For now, no authentication setup needed
        // This can be extended when authentication is implemented
    }

    protected function givenThereAreNoTasks(): void
    {
        // Database is clean in test environment
    }

    protected function givenThereAreNoUsers(): void
    {
        // Database is clean in test environment
    }

    protected function givenIHaveCreatedATaskWith(array $attributes): string
    {
        $this->whenICreateATaskWith($attributes);
        $this->thenTheTaskShouldBeCreatedSuccessfully();
        return $this->lastCreatedResourceId;
    }

    protected function givenIHaveCreatedAUserWith(array $attributes): string
    {
        $this->whenICreateAUserWith($attributes);
        $this->thenTheUserShouldBeCreatedSuccessfully();
        return $this->lastCreatedResourceId;
    }

    // === When (Action) Methods ===

    protected function whenIRequestTheListOfTasks(): void
    {
        $this->client->request('GET', '/api/tasks', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        
        $this->lastResponse = $this->parseJsonResponse($this->client->getResponse());
    }

    protected function whenICreateATaskWith(array $attributes): void
    {
        $this->client->request('POST', '/api/tasks', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($attributes));
        
        $this->lastResponse = $this->parseJsonResponse($this->client->getResponse());
        
        if (isset($this->lastResponse['id'])) {
            $this->lastCreatedResourceId = $this->lastResponse['id'];
        }
    }

    protected function whenIRequestTheTaskWithId(string $taskId): void
    {
        $this->client->request('GET', "/api/tasks/{$taskId}", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        
        $this->lastResponse = $this->parseJsonResponse($this->client->getResponse());
    }

    protected function whenICompleteTheTaskWithId(string $taskId, string $userId = 'test-user-id'): void
    {
        $this->client->request('POST', "/api/tasks/{$taskId}/complete", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['userId' => $userId]));
        
        $this->lastResponse = $this->parseJsonResponse($this->client->getResponse());
    }

    protected function whenIApproveTheTaskWithId(string $taskId, string $adminId = 'test-admin-id'): void
    {
        $this->client->request('POST', "/api/tasks/{$taskId}/approve", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['adminId' => $adminId]));
        
        $this->lastResponse = $this->parseJsonResponse($this->client->getResponse());
    }

    protected function whenIRequestTheListOfUsers(): void
    {
        $this->client->request('GET', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        
        $this->lastResponse = $this->parseJsonResponse($this->client->getResponse());
    }

    protected function whenICreateAUserWith(array $attributes): void
    {
        $this->client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($attributes));
        
        $this->lastResponse = $this->parseJsonResponse($this->client->getResponse());
        
        if (isset($this->lastResponse['id'])) {
            $this->lastCreatedResourceId = $this->lastResponse['id'];
        }
    }

    protected function whenIRequestTheUserWithId(string $userId): void
    {
        $this->client->request('GET', "/api/users/{$userId}", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        
        $this->lastResponse = $this->parseJsonResponse($this->client->getResponse());
    }

    protected function whenIRequestANonExistentTask(): void
    {
        $this->whenIRequestTheTaskWithId('00000000-0000-0000-0000-000000000000');
    }

    protected function whenIRequestANonExistentUser(): void
    {
        $this->whenIRequestTheUserWithId('00000000-0000-0000-0000-000000000000');
    }

    // === Then (Assertion) Methods ===

    protected function thenTheResponseShouldBeSuccessful(): void
    {
        $this->assertResponseIsSuccessful();
    }

    protected function thenTheResponseStatusCodeShouldBe(int $expectedStatusCode): void
    {
        $this->assertResponseStatusCodeSame($expectedStatusCode);
    }

    protected function thenTheResponseShouldBeInJsonFormat(): void
    {
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }

    protected function thenIShouldReceiveAnEmptyListOfTasks(): void
    {
        $this->assertIsArray($this->lastResponse);
        $this->assertArrayHasKey('tasks', $this->lastResponse);
        $this->assertIsArray($this->lastResponse['tasks']);
    }

    protected function thenIShouldReceiveAnEmptyListOfUsers(): void
    {
        $this->assertIsArray($this->lastResponse);
        $this->assertArrayHasKey('users', $this->lastResponse);
        $this->assertIsArray($this->lastResponse['users']);
    }

    protected function thenTheTaskShouldBeCreatedSuccessfully(): void
    {
        $this->thenTheResponseStatusCodeShouldBe(201);
        $this->thenTheResponseShouldBeInJsonFormat();
        $this->assertArrayHasKey('id', $this->lastResponse);
        $this->assertNotEmpty($this->lastResponse['id']);
    }

    protected function thenTheUserShouldBeCreatedSuccessfully(): void
    {
        $this->thenTheResponseStatusCodeShouldBe(201);
        $this->thenTheResponseShouldBeInJsonFormat();
        $this->assertArrayHasKey('id', $this->lastResponse);
        $this->assertNotEmpty($this->lastResponse['id']);
    }

    protected function thenTheResponseShouldContainTaskAttributes(array $expectedAttributes): void
    {
        foreach ($expectedAttributes as $key => $expectedValue) {
            $this->assertArrayHasKey($key, $this->lastResponse, "Expected attribute '{$key}' not found in response");
            $this->assertSame($expectedValue, $this->lastResponse[$key], "Attribute '{$key}' has unexpected value");
        }
    }

    protected function thenTheResponseShouldContainUserAttributes(array $expectedAttributes): void
    {
        foreach ($expectedAttributes as $key => $expectedValue) {
            $this->assertArrayHasKey($key, $this->lastResponse, "Expected attribute '{$key}' not found in response");
            $this->assertSame($expectedValue, $this->lastResponse[$key], "Attribute '{$key}' has unexpected value");
        }
    }

    protected function thenTheTaskShouldHaveStatus(string $expectedStatus): void
    {
        $this->assertArrayHasKey('status', $this->lastResponse);
        $this->assertSame($expectedStatus, $this->lastResponse['status']);
    }

    protected function thenIShouldReceiveANotFoundError(): void
    {
        $this->thenTheResponseStatusCodeShouldBe(404);
        $this->thenTheResponseShouldBeInJsonFormat();
        $this->assertArrayHasKey('error', $this->lastResponse);
    }

    protected function thenTheResponseShouldContainAValidId(): void
    {
        $this->assertArrayHasKey('id', $this->lastResponse);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $this->lastResponse['id'],
            'Response does not contain a valid UUID'
        );
    }

    // === Helper Methods ===

    private function parseJsonResponse(Response $response): ?array
    {
        $content = $response->getContent();
        if (empty($content)) {
            return null;
        }
        
        return json_decode($content, true);
    }

    protected function getLastCreatedResourceId(): string
    {
        $this->assertNotNull($this->lastCreatedResourceId, 'No resource was created in the previous action');
        return $this->lastCreatedResourceId;
    }
}
