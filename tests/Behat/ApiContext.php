<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Uid\Uuid;
use PHPUnit\Framework\Assert;

final class ApiContext implements Context
{
    private ?Response $response = null;
    private ?array $responseData = null;
    private array $requestData = [];
    private ?string $lastCreatedId = null;
    private string $lastResourceType = 'task'; // Track resource type: 'task' or 'user'
    private KernelInterface $kernel;

    // Test user IDs - can be overridden via environment variables
    private string $testUserId;
    private string $testAdminId;

    public function __construct()
    {
        // Initialize kernel for testing
        require_once dirname(__DIR__) . '/bootstrap.php';
        $this->kernel = new \App\Kernel('test', true);
        $this->kernel->boot();
        
        // Initialize test IDs - generate UUIDs or use env vars
        $this->testUserId = $_ENV['TEST_USER_ID'] ?? $this->generateTestUuid();
        $this->testAdminId = $_ENV['TEST_ADMIN_ID'] ?? $this->generateTestUuid();
    }

    private function generateTestUuid(): string
    {
        return Uuid::v4()->toRfc4122();
    }

    /**
     * @When I view the task list
     */
    public function iViewTheTaskList(): void
    {
        $this->sendRequest('GET', '/api/tasks');
    }

    /**
     * @When I view the user list
     */
    public function iViewTheUserList(): void
    {
        $this->sendRequest('GET', '/api/users');
    }

    /**
     * @Then I should see an empty task list
     */
    public function iShouldSeeAnEmptyTaskList(): void
    {
        $this->assertResponseIsSuccessful();
        $this->parseJsonResponse();
        Assert::assertArrayHasKey('tasks', $this->responseData);
        Assert::assertIsArray($this->responseData['tasks']);
    }

    /**
     * @Then I should see an empty user list
     */
    public function iShouldSeeAnEmptyUserList(): void
    {
        $this->assertResponseIsSuccessful();
        $this->parseJsonResponse();
        Assert::assertArrayHasKey('users', $this->responseData);
        Assert::assertIsArray($this->responseData['users']);
    }

    /**
     * @When I create a task with the following details:
     */
    public function iCreateATaskWithTheFollowingDetails(TableNode $table): void
    {
        $data = [];
        foreach ($table->getRowsHash() as $key => $value) {
            $data[$key] = is_numeric($value) ? (int)$value : $value;
        }
        $this->lastResourceType = 'task';
        $this->sendRequest('POST', '/api/tasks', $data);
    }

    /**
     * @When I create a user account with the following details:
     */
    public function iCreateAUserAccountWithTheFollowingDetails(TableNode $table): void
    {
        $data = [];
        foreach ($table->getRowsHash() as $key => $value) {
            $data[$key] = $value;
        }
        $this->lastResourceType = 'user';
        $this->sendRequest('POST', '/api/users', $data);
    }

    /**
     * @Given I have created a task :name worth :points points scheduled :frequency
     */
    public function iHaveCreatedATaskWorthPointsScheduled(string $name, int $points, string $frequency): void
    {
        $data = [
            'name' => $name,
            'description' => 'Test description',
            'points' => $points,
            'frequency' => $frequency,
        ];
        $this->lastResourceType = 'task';
        $this->sendRequest('POST', '/api/tasks', $data);
        $this->parseJsonResponse();
        $this->lastCreatedId = $this->responseData['id'] ?? null;
    }

    /**
     * @Given I have created a user :name with email :email
     */
    public function iHaveCreatedAUserWithEmail(string $name, string $email): void
    {
        $data = [
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'role' => 'ROLE_USER',
        ];
        $this->lastResourceType = 'user';
        $this->sendRequest('POST', '/api/users', $data);
        $this->parseJsonResponse();
        $this->lastCreatedId = $this->responseData['id'] ?? null;
    }

    /**
     * @When I view the task details
     * @When I view the user account details
     */
    public function iViewTheDetails(): void
    {
        Assert::assertNotNull($this->lastCreatedId, 'No resource was created before');
        
        // Use tracked resource type instead of guessing from response
        $endpoint = $this->lastResourceType === 'user' ? 'users' : 'tasks';
        
        $this->sendRequest('GET', "/api/{$endpoint}/{$this->lastCreatedId}");
    }

    /**
     * @When I mark the task as completed
     * @Given I have marked the task as completed
     */
    public function iMarkTheTaskAsCompleted(): void
    {
        Assert::assertNotNull($this->lastCreatedId, 'No task was created before');
        $this->sendRequest('POST', "/api/tasks/{$this->lastCreatedId}/complete", [
            'userId' => $this->testUserId,
        ]);
    }

    /**
     * @When the admin approves the task
     */
    public function theAdminApprovesTheTask(): void
    {
        Assert::assertNotNull($this->lastCreatedId, 'No task was created before');
        $this->sendRequest('POST', "/api/tasks/{$this->lastCreatedId}/approve", [
            'adminId' => $this->testAdminId,
        ]);
    }

    /**
     * @When I try to view a task that doesn't exist
     */
    public function iTryToViewATaskThatDoesntExist(): void
    {
        $this->sendRequest('GET', '/api/tasks/00000000-0000-0000-0000-000000000000');
    }

    /**
     * @When I try to view a user account that doesn't exist
     */
    public function iTryToViewAUserAccountThatDoesntExist(): void
    {
        $this->sendRequest('GET', '/api/users/00000000-0000-0000-0000-000000000000');
    }

    /**
     * @Then the task should be created successfully
     * @Then the user account should be created successfully
     */
    public function theResourceShouldBeCreatedSuccessfully(): void
    {
        Assert::assertEquals(201, $this->response->getStatusCode(), 
            'Expected status code 201 but got ' . $this->response->getStatusCode());
        $this->parseJsonResponse();
        Assert::assertArrayHasKey('id', $this->responseData);
        $this->lastCreatedId = $this->responseData['id'];
    }

    /**
     * @Then the task should have the name :name
     * @Then I should see the task name :name
     * @Then the user should have the name :name
     * @Then I should see the user name :name
     */
    public function theResourceShouldHaveTheName(string $name): void
    {
        $this->parseJsonResponse();
        Assert::assertEquals($name, $this->responseData['name']);
    }

    /**
     * @Then the task should be worth :points points
     * @Then I should see it is worth :points points
     */
    public function theTaskShouldBeWorthPoints(int $points): void
    {
        $this->parseJsonResponse();
        Assert::assertEquals($points, $this->responseData['points']);
    }

    /**
     * @Then the task should be scheduled :frequency
     * @Then I should see it is scheduled :frequency
     */
    public function theTaskShouldBeScheduled(string $frequency): void
    {
        $this->parseJsonResponse();
        Assert::assertEquals($frequency, $this->responseData['frequency']);
    }

    /**
     * @Then the task should be in :status status
     * @Then the task status should be :status
     */
    public function theTaskShouldBeInStatus(string $status): void
    {
        $this->parseJsonResponse();
        Assert::assertEquals($status, $this->responseData['status']);
    }

    /**
     * @Then the user should have the email :email
     * @Then I should see the email :email
     */
    public function theUserShouldHaveTheEmail(string $email): void
    {
        $this->parseJsonResponse();
        Assert::assertEquals($email, $this->responseData['email']);
    }

    /**
     * @Then the user should have the role :role
     */
    public function theUserShouldHaveTheRole(string $role): void
    {
        $this->parseJsonResponse();
        Assert::assertEquals($role, $this->responseData['role']);
    }

    /**
     * @Then I should see a :message error
     */
    public function iShouldSeeAnError(string $message): void
    {
        Assert::assertEquals(404, $this->response->getStatusCode());
        $this->parseJsonResponse();
        Assert::assertArrayHasKey('error', $this->responseData);
        Assert::assertStringContainsString($message, $this->responseData['error']);
    }

    private function sendRequest(string $method, string $uri, ?array $data = null): void
    {
        $content = $data ? json_encode($data) : null;
        
        $request = Request::create(
            $uri,
            $method,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $content
        );

        $this->response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $this->response);
    }

    private function parseJsonResponse(): void
    {
        if ($this->responseData === null) {
            $content = $this->response->getContent();
            $this->responseData = json_decode($content, true);
            Assert::assertIsArray($this->responseData, 'Response is not valid JSON: ' . $content);
        }
    }

    private function assertResponseIsSuccessful(): void
    {
        Assert::assertTrue(
            $this->response->getStatusCode() >= 200 && $this->response->getStatusCode() < 300,
            sprintf('Expected successful response but got %d', $this->response->getStatusCode())
        );
    }
}
