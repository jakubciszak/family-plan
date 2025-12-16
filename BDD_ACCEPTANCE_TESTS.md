# BDD-Style Acceptance Tests Summary

## Overview

This implementation provides **Behavior-Driven Development (BDD)** style acceptance tests using **native Symfony testing tools** without requiring Behat or other external BDD frameworks.

## Implementation Details

### Technology Stack
- **PHPUnit** (via Symfony phpunit-bridge)
- **Symfony WebTestCase** for API testing
- **PHP 8 Attributes** for test metadata
- **Docker** with PHP 8.5 for test environment

### Architecture

```
tests/Acceptance/
├── AcceptanceTestCase.php      # Base class with behavioral methods
├── TaskManagementTest.php      # 7 task management scenarios
├── UserManagementTest.php      # 6 user management scenarios
└── README.md                   # Complete testing guide
```

## Behavioral Method Categories

### Given (Setup Preconditions)
Methods that establish the initial state before testing:

```php
$this->givenIAmAnAuthenticatedUser();
$this->givenThereAreNoTasks();
$taskId = $this->givenIHaveCreatedATaskWith(['name' => 'Clean kitchen']);
```

### When (Perform Actions)
Methods that execute the behavior being tested:

```php
$this->whenIRequestTheListOfTasks();
$this->whenICreateATaskWith(['name' => 'Task', 'points' => 100]);
$this->whenICompleteTheTaskWithId($taskId);
```

### Then (Assert Outcomes)
Methods that verify expected results:

```php
$this->thenTheResponseShouldBeSuccessful();
$this->thenTheTaskShouldBeCreatedSuccessfully();
$this->thenTheTaskShouldHaveStatus('completed');
```

## Test Coverage

### Task Management (7 scenarios)
1. ✅ List empty tasks
2. ✅ Create task with valid attributes
3. ✅ Retrieve task by ID
4. ✅ Complete a task
5. ✅ Approve completed task
6. ✅ Handle non-existent task (404)
7. ✅ List multiple created tasks

### User Management (6 scenarios)
1. ✅ List empty users
2. ✅ Create user with valid attributes
3. ✅ Retrieve user by ID
4. ✅ Handle non-existent user (404)
5. ✅ List multiple created users
6. ✅ Create admin user

## Benefits Over Traditional Tests

### Readability
**Traditional PHPUnit:**
```php
public function testCreateTask(): void
{
    $response = $this->postJson('/api/tasks', ['name' => 'Task']);
    $this->assertEquals(201, $response->getStatusCode());
}
```

**BDD-Style:**
```php
#[Test]
public function shouldCreateTaskWithValidAttributes(): void
{
    // Given
    $this->givenIAmAnAuthenticatedUser();
    
    // When
    $this->whenICreateATaskWith(['name' => 'Task']);
    
    // Then
    $this->thenTheTaskShouldBeCreatedSuccessfully();
}
```

### Self-Documentation
Test names and structure serve as living documentation:
- Non-technical stakeholders can understand test scenarios
- Developers quickly understand system behavior
- New team members learn API contracts from tests

### Maintainability
- HTTP implementation details hidden in base class
- When endpoints change, update once in base class
- Tests remain stable and focused on behavior

### Reusability
Common patterns defined once and used everywhere:
```php
// Create task in setup
$taskId = $this->givenIHaveCreatedATaskWith($attributes);

// Now focus on testing the specific behavior
$this->whenICompleteTheTaskWithId($taskId);
```

## Running Tests

### All Acceptance Tests
```bash
docker compose exec php vendor/bin/phpunit tests/Acceptance
```

### With Readable Output
```bash
docker compose exec php vendor/bin/phpunit tests/Acceptance --testdox
```

**Output:**
```
Task Management
 ✔ Should return empty list when no tasks exist
 ✔ Should create task with valid attributes
 ✔ Should retrieve task by id
 ✔ Should mark task as completed
 ✔ Should approve completed task
 ✔ Should return not found for non existent task
 ✔ Should list all created tasks

User Management
 ✔ Should return empty list when no users exist
 ✔ Should create user with valid attributes
 ✔ Should retrieve user by id
 ✔ Should return not found for non existent user
 ✔ Should list all created users
 ✔ Should create admin user
```

### Specific Test
```bash
docker compose exec php vendor/bin/phpunit tests/Acceptance/TaskManagementTest.php --filter shouldCreateTask
```

## CI/CD Integration

Tests run automatically in GitHub Actions:

```yaml
- name: Run PHPUnit tests
  run: vendor/bin/phpunit --testdox
```

This includes both:
- Traditional API tests (`tests/Api/`)
- BDD-style acceptance tests (`tests/Acceptance/`)

## PHP 8 Attributes

Modern PHP 8 attribute syntax provides:

```php
use PHPUnit\Framework\Attributes\Test;

#[Test]
public function shouldDoSomething(): void
{
    // Test implementation
}
```

**Benefits:**
- Native PHP syntax (not comments)
- Better IDE support and autocomplete
- Type-safe and refactor-friendly
- Improved performance (no annotation parsing)

## Extending Tests

### Adding New Scenarios

1. **Write scenario description:**
```php
/**
 * Scenario: Delete a completed task
 *   Given I have created and completed a task
 *   When I delete the task
 *   Then the task should be removed from the system
 */
#[Test]
public function shouldDeleteCompletedTask(): void
```

2. **Implement with Given-When-Then:**
```php
{
    // Given
    $taskId = $this->givenIHaveCreatedATaskWith(['name' => 'Task']);
    $this->whenICompleteTheTaskWithId($taskId);
    
    // When
    $this->whenIDeleteTheTaskWithId($taskId);
    
    // Then
    $this->thenTheTaskShouldBeDeleted();
}
```

3. **Add helper methods to base class if needed:**
```php
// In AcceptanceTestCase.php
protected function whenIDeleteTheTaskWithId(string $taskId): void
{
    $this->client->request('DELETE', "/api/tasks/{$taskId}");
    $this->lastResponse = $this->parseJsonResponse($this->client->getResponse());
}
```

## Future Path

When Behat 4.x adds Symfony 8.0 support:
- Convert test scenarios to Gherkin `.feature` files
- Reuse behavioral methods as Behat step definitions
- Run same scenarios with both PHPUnit and Behat
- Allow product owners to write feature files directly

Until then, these PHPUnit-based tests provide identical benefits with native Symfony tools.

## Related Documentation

- `tests/Acceptance/README.md` - Complete BDD testing guide
- `BEHAT_STATUS.md` - Behat compatibility status
- `features/` - Prepared Behat infrastructure (for future use)

## Conclusion

This implementation demonstrates that **you don't need Behat to write BDD-style tests**. By using descriptive method names, Given-When-Then structure, and PHP 8 attributes, we achieve the same clarity and expressiveness with native Symfony tools.

The tests serve as:
- ✅ Executable specifications
- ✅ Living documentation
- ✅ Regression safety net
- ✅ API contract examples
