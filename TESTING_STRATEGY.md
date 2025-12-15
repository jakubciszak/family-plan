# Testing Strategy

## Backend API Testing

### Why PHPUnit Instead of Behat?

**Original Requirement:** "To prove that everything works add behat tests"

**Current Implementation:** PHPUnit with Symfony WebTestCase

**Rationale:**
- Behat does not yet support Symfony 8.0 (the project uses Symfony 8.0)
- Latest Behat version (3.29.0) supports up to Symfony 7.x and PHP < 8.5
- Project requirement: PHP 8.5, Symfony 8.0

**Alternative Solution:**
PHPUnit with Symfony WebTestCase provides equivalent functionality:
- Full HTTP request/response testing
- JSON API testing
- Database integration
- Symfony service container access
- BDD-style test organization is possible with PHPUnit

### API Test Structure

Located in `tests/Api/`:

```
tests/Api/
├── ApiTestCase.php       # Base test class with helper methods
├── TaskApiTest.php       # Tests for task management endpoints
└── UserApiTest.php       # Tests for user management endpoints
```

### Running API Tests

```bash
# All API tests
docker compose run --rm php vendor/bin/phpunit tests/Api/

# Specific test class
docker compose run --rm php vendor/bin/phpunit tests/Api/TaskApiTest.php

# With test coverage
docker compose run --rm php vendor/bin/phpunit tests/Api/ --coverage-text
```

### Example Test

```php
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
    $this->assertSame('Test Task', $data['name']);
    $this->assertSame(100, $data['points']);
}
```

This approach:
- Tests actual HTTP endpoints
- Validates JSON responses
- Checks status codes
- Verifies data integrity
- Uses real database (can be configured for test environment)

## Frontend E2E Testing

### Recommended: Playwright or Cypress

For frontend end-to-end testing, use modern tools:

**Playwright** (Recommended):
```bash
npm install --save-dev @playwright/test
npx playwright test
```

**Cypress** (Alternative):
```bash
npm install --save-dev cypress
npx cypress open
```

### Example E2E Test Structure

```javascript
// tests/e2e/auth.spec.js
test('user can login', async ({ page }) => {
  await page.goto('http://localhost:8080/app');
  
  await page.fill('input[name="email"]', 'admin@familyplan.local');
  await page.fill('input[name="password"]', 'admin123');
  await page.click('button[type="submit"]');
  
  await expect(page.locator('.user-info')).toContainText('Welcome');
});

// tests/e2e/tasks.spec.js
test('user can create a task', async ({ page }) => {
  // Login first
  await loginAs(page, 'admin@familyplan.local');
  
  // Create task
  await page.click('text=Create Task');
  await page.fill('input[name="name"]', 'New Task');
  await page.fill('input[name="points"]', '50');
  await page.click('button[type="submit"]');
  
  // Verify task appears
  await expect(page.locator('.task-card')).toContainText('New Task');
});
```

## Migration from Behat to PHPUnit

If Behat support becomes available in the future, migration is straightforward:

### Behat Feature File
```gherkin
Feature: Task Management
  As a user
  I want to manage tasks
  So that I can track family chores

  Scenario: Create a new task
    Given I am authenticated as "admin@familyplan.local"
    When I create a task with:
      | name        | Clean the kitchen |
      | points      | 50               |
      | frequency   | daily            |
    Then the task should be created
    And the response status should be 201
```

### Equivalent PHPUnit Test
```php
public function testCreateTaskAsAuthenticatedUser(): void
{
    // Given I am authenticated
    $this->authenticateAs('admin@familyplan.local');
    
    // When I create a task with...
    $response = $this->postJson('/api/tasks', [
        'name' => 'Clean the kitchen',
        'points' => 50,
        'frequency' => 'daily',
    ]);
    
    // Then the task should be created
    $data = $this->assertJsonResponse($response, 201);
    $this->assertArrayHasKey('id', $data);
    $this->assertSame('Clean the kitchen', $data['name']);
}
```

Both approaches test the same functionality, but PHPUnit is:
- Already integrated with Symfony
- No additional dependencies needed
- Better IDE support
- Faster execution
- Easier debugging

## Test-Driven Development (TDD) Approach

The refactoring followed TDD principles:

1. **Red Phase**: Created failing API tests first
   - `TaskApiTest.php` - Test task CRUD operations
   - `UserApiTest.php` - Test user management
   
2. **Green Phase**: Implemented API controllers to make tests pass
   - `TaskApiController.php` - REST endpoints for tasks
   - `UserApiController.php` - REST endpoints for users
   - `AuthApiController.php` - Authentication endpoints

3. **Refactor Phase**: Cleaned up and documented
   - Added comprehensive documentation
   - Ensured consistency across endpoints
   - Maintained DDD architecture

## Continuous Integration

For CI/CD pipelines, add test execution:

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  backend-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Build Docker image
        run: docker compose build php
      - name: Install dependencies
        run: docker compose run --rm php composer install
      - name: Run API tests
        run: docker compose run --rm php vendor/bin/phpunit tests/Api/
        
  frontend-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      - name: Install dependencies
        run: npm install
      - name: Build frontend
        run: npm run build
      - name: Run E2E tests
        run: npx playwright test
```

## Summary

- **Backend**: PHPUnit provides robust API testing (Behat alternative)
- **Frontend**: Playwright/Cypress for E2E tests (to be implemented)
- **TDD**: Development followed test-driven approach
- **CI/CD**: Ready for automated testing pipelines
- **Future**: Can migrate to Behat when Symfony 8 support is added
