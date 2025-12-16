# Acceptance Tests with Behavior-Driven Language

## Overview

This directory contains **acceptance tests** (E2E tests) for the Family Plan API, written using **behavior-driven language** inspired by BDD practices. While we use PHPUnit as the test runner (part of Symfony's test pack), the tests are structured to read like natural language specifications.

## Philosophy

These tests follow the **Given-When-Then** pattern popularized by BDD frameworks like Behat, Cucumber, and SpecFlow:

- **Given**: Set up the initial state (preconditions)
- **When**: Perform the action being tested
- **Then**: Verify the expected outcome

## Structure

### Base Class: `AcceptanceTestCase`

The `AcceptanceTestCase` provides reusable methods that read like natural language:

```php
// Given methods - Setup preconditions
$this->givenIAmAnAuthenticatedUser();
$taskId = $this->givenIHaveCreatedATaskWith(['name' => 'Clean kitchen']);

// When methods - Perform actions
$this->whenIRequestTheListOfTasks();
$this->whenICreateATaskWith($attributes);
$this->whenICompleteTheTaskWithId($taskId);

// Then methods - Assert outcomes
$this->thenTheResponseShouldBeSuccessful();
$this->thenTheTaskShouldBeCreatedSuccessfully();
$this->thenTheTaskShouldHaveStatus('completed');
```

### Test Classes

Each test class focuses on a specific feature or domain:

- `TaskManagementTest` - Task creation, completion, and approval flows
- `UserManagementTest` - User registration and management flows

## Example Test

Here's how a typical test reads:

```php
use PHPUnit\Framework\Attributes\Test;

/**
 * Scenario: Create a new task
 *   Given I am an authenticated user
 *   When I create a task with valid attributes
 *   Then the task should be created successfully
 *   And the response should contain all task attributes
 */
#[Test]
public function shouldCreateTaskWithValidAttributes(): void
{
    // Given
    $this->givenIAmAnAuthenticatedUser();
    
    // When
    $taskAttributes = [
        'name' => 'Clean the kitchen',
        'description' => 'Wash dishes and wipe counters',
        'points' => 100,
        'frequency' => 'daily',
    ];
    $this->whenICreateATaskWith($taskAttributes);
    
    // Then
    $this->thenTheTaskShouldBeCreatedSuccessfully();
    $this->thenTheResponseShouldContainAValidId();
    $this->thenTheResponseShouldContainTaskAttributes([
        'name' => 'Clean the kitchen',
        'description' => 'Wash dishes and wipe counters',
        'points' => 100,
        'frequency' => 'daily',
    ]);
}
```

## Benefits of This Approach

### 1. **Readability**
Tests read like specifications that non-technical stakeholders can understand.

### 2. **Self-Documenting**
Test names and structure clearly describe what the system should do.

### 3. **Reusability**
Common actions are defined once in the base class and reused across all tests.

### 4. **Maintainability**
When API endpoints change, you only need to update the base class methods.

### 5. **Native Symfony Integration**
Uses Symfony's `WebTestCase` and test infrastructure - no external dependencies needed.

## Running the Tests

```bash
# Run all acceptance tests
docker compose exec php vendor/bin/phpunit tests/Acceptance

# Run specific test class
docker compose exec php vendor/bin/phpunit tests/Acceptance/TaskManagementTest.php

# Run with verbose output
docker compose exec php vendor/bin/phpunit tests/Acceptance --testdox

# Run specific test method
docker compose exec php vendor/bin/phpunit tests/Acceptance/TaskManagementTest.php --filter shouldCreateTaskWithValidAttributes
```

## CI Integration

These tests run automatically in GitHub Actions:

```yaml
- name: Run PHPUnit tests
  run: vendor/bin/phpunit --testdox
```

The `--testdox` flag outputs test results in a human-readable format:

```
Task Management
 ✔ Should return empty list when no tasks exist
 ✔ Should create task with valid attributes
 ✔ Should retrieve task by id
 ✔ Should mark task as completed
 ✔ Should approve completed task
```

## Writing New Tests

### 1. Identify the Scenario

Think about user stories or acceptance criteria:
- "As a user, I want to create a task so that I can track my chores"
- "As an admin, I want to approve completed tasks to award points"

### 2. Write the Test Name

Use descriptive names that express intent:
```php
#[Test]
public function shouldCreateTaskWithValidAttributes(): void

#[Test]
public function shouldRejectTaskWithMissingName(): void

#[Test]
public function shouldAllowAdminToApproveTask(): void
```

### 3. Structure with Given-When-Then

```php
use PHPUnit\Framework\Attributes\Test;

#[Test]
public function shouldDoSomething(): void
{
    // Given - Setup
    $this->givenSomeInitialState();
    
    // When - Action
    $this->whenIPerformAnAction();
    
    // Then - Assertions
    $this->thenTheExpectedOutcomeShouldOccur();
}
```

### 4. Add New Helper Methods as Needed

If you need a new action or assertion, add it to `AcceptanceTestCase`:

```php
// In AcceptanceTestCase.php
protected function whenIDeleteTheTaskWithId(string $taskId): void
{
    $this->client->request('DELETE', "/api/tasks/{$taskId}");
    $this->lastResponse = $this->parseJsonResponse($this->client->getResponse());
}

protected function thenTheTaskShouldBeDeleted(): void
{
    $this->thenTheResponseStatusCodeShouldBe(204);
}
```

## Comparison with Traditional PHPUnit Tests

### Traditional Style (tests/Api/TaskApiTest.php)
```php
public function testCreateTask(): void
{
    $taskData = ['name' => 'Test Task', 'points' => 100];
    $response = $this->postJson('/api/tasks', $taskData);
    $data = $this->assertJsonResponse($response, 201);
    $this->assertArrayHasKey('id', $data);
}
```

### BDD Style (tests/Acceptance/TaskManagementTest.php)
```php
use PHPUnit\Framework\Attributes\Test;

#[Test]
public function shouldCreateTaskWithValidAttributes(): void
{
    // Given
    $this->givenIAmAnAuthenticatedUser();
    
    // When
    $this->whenICreateATaskWith(['name' => 'Test Task', 'points' => 100]);
    
    // Then
    $this->thenTheTaskShouldBeCreatedSuccessfully();
    $this->thenTheResponseShouldContainAValidId();
}
```

The BDD style is more verbose but significantly more readable and expressive.

## PHP 8 Attributes

These tests use PHP 8 attributes (`#[Test]`) instead of annotations (`@test`) for marking test methods. This provides:
- Better IDE support and type checking
- Native PHP syntax instead of comment parsing
- Improved performance and reliability

## Best Practices

1. **One Scenario Per Test**: Each test should verify one specific behavior
2. **Clear Test Names**: Use `should...` naming convention
3. **Document Scenarios**: Add docblocks explaining the scenario in Given-When-Then format
4. **Reuse Helper Methods**: Don't duplicate assertion logic
5. **Keep Tests Independent**: Each test should be able to run in isolation
6. **Test Business Behavior**: Focus on what the system does, not how it does it

## Future Enhancements

When Behat 4.x adds Symfony 8.0 support, we can:
- Convert these scenarios to Gherkin `.feature` files
- Share step definitions between Behat and PHPUnit
- Run the same tests with both frameworks
- Allow non-technical stakeholders to read raw feature files

Until then, these PHPUnit-based acceptance tests provide the same level of clarity and documentation.
