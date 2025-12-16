# Behat E2E Tests for Family Plan API

This directory contains Behat acceptance tests for the Family Plan backend API, written using business-oriented language following BDD (Behavior-Driven Development) principles.

## Overview

The Behat tests are written in Gherkin syntax with natural, user-friendly language that describes business scenarios rather than technical API calls.

### Example: Before vs After

**Before (Technical):**
```gherkin
When I send a "POST" request to "/api/tasks" with JSON:
  """
  {"name": "Clean kitchen", "points": 100}
  """
```

**After (Business-Oriented):**
```gherkin
When I create a task with the following details:
  | name   | Clean kitchen |
  | points | 100           |
```

## Feature Files

### Task Management (`task_management.feature`)
Tests for family task management including:
- Creating tasks with points and frequency
- Viewing task lists and details
- Completing tasks
- Admin approval workflow

### User Management (`user_management.feature`)
Tests for family member account management including:
- Creating user accounts
- Viewing user lists and details
- Role assignment (USER/ADMIN)

## Running Tests

### Prerequisites

**Note:** Behat 3.x currently requires Symfony ^5.4 || ^6.4 || ^7.0. Since this project uses Symfony 8.0, you have two options:

1. **Wait for Behat 4.x stable release** (supports Symfony 8.0)
2. **Use the provided GitHub Actions workflow** which will be configured to work once Behat 4.x is stable

### With Docker

```bash
# Install dependencies (once Behat 4.x is stable or using compatibility workaround)
docker compose exec php composer install

# Run all Behat tests
docker compose exec php vendor/bin/behat

# Run specific feature
docker compose exec php vendor/bin/behat features/api/task_management.feature

# Run with verbose output
docker compose exec php vendor/bin/behat --verbose
```

### Locally

```bash
# Install dependencies
composer install

# Run tests
vendor/bin/behat

# Run with specific profile
vendor/bin/behat --profile=api
```

## Test Structure

```
features/
└── api/
    ├── task_management.feature    # Task-related scenarios
    └── user_management.feature     # User-related scenarios

tests/
└── Behat/
    └── ApiContext.php             # Step definitions with business logic
```

## Writing New Tests

### 1. Use Business Language

Focus on **what** the user wants to do, not **how** it's implemented:

```gherkin
# Good ✓
When I create a task "Clean kitchen" worth 50 points
Then the task should be created successfully

# Avoid ✗
When I send a POST request to /api/tasks
Then the response code should be 201
```

### 2. Follow the Given-When-Then Pattern

- **Given**: Setup the initial state
- **When**: Perform the action
- **Then**: Verify the outcome

```gherkin
Scenario: Complete an assigned task
  Given I have created a task "Dishes" worth 25 points
  When I mark the task as completed
  Then the task status should be "completed"
```

### 3. Use Tables for Multiple Values

```gherkin
When I create a user account with the following details:
  | name     | John Doe          |
  | email    | john@example.com  |
  | role     | ROLE_USER         |
```

## Step Definitions

All step definitions are in `tests/Behat/ApiContext.php` and follow business-oriented naming:

- `I view the task list` - Lists all tasks
- `I create a task with the following details` - Creates a new task
- `I mark the task as completed` - Completes a task
- `the admin approves the task` - Admin approves completed task
- `the task should be created successfully` - Verifies successful creation

## GitHub Actions Integration

The `.github/workflows/backend-tests.yml` workflow automatically:
1. Sets up PHP 8.5 environment
2. Installs dependencies
3. Runs PHPUnit unit tests
4. Runs Behat acceptance tests
5. Reports results

## Symfony 8.0 Compatibility

**Current Status:** Behat 3.x doesn't support Symfony 8.0 yet.

**Solutions:**
1. Behat 4.x-dev supports Symfony 8.0 but ecosystem extensions need updates
2. The project is configured to use Behat 4.x-dev once dependencies are resolved
3. PHPUnit tests can serve as interim E2E tests

**Configuration:**
```json
"require-dev": {
    "behat/behat": "3.x-dev",  // Will update to 4.x when stable
    "phpunit/phpunit": "^11.0"
}
```

## TDD Workflow

1. Write a failing Behat scenario describing the desired behavior
2. Run the test and see it fail
3. Implement the minimum code to make it pass
4. Refactor while keeping tests green
5. Repeat

Example:
```bash
# 1. Write scenario
vim features/api/new_feature.feature

# 2. Run and see it fail
vendor/bin/behat features/api/new_feature.feature

# 3. Implement feature
vim src/...

# 4. Run until green
vendor/bin/behat features/api/new_feature.feature
```

## Best Practices

1. **One scenario per behavior**: Each scenario should test one specific behavior
2. **Descriptive names**: Scenario names should clearly state what's being tested
3. **Independent scenarios**: Each scenario should be able to run independently
4. **Avoid implementation details**: Focus on business value, not technical details
5. **Use background for common setup**: DRY principle for repeated Given steps

## Troubleshooting

### Composer dependency issues
```bash
# Clear composer cache
composer clear-cache

# Remove lock file and reinstall
rm composer.lock
composer install
```

### Tests failing
```bash
# Run with verbose output
vendor/bin/behat --verbose

# Check specific step definition
vendor/bin/behat --dry-run
```

### Database issues
```bash
# Ensure test database is migrated
docker compose exec php php bin/console doctrine:migrations:migrate --env=test
```

## Contributing

When adding new test scenarios:
1. Use business-oriented language
2. Add step definitions to ApiContext.php
3. Update this README if adding new features
4. Ensure tests pass before committing

## Resources

- [Behat Documentation](https://docs.behat.org/)
- [Gherkin Syntax](https://cucumber.io/docs/gherkin/)
- [BDD Best Practices](https://cucumber.io/docs/bdd/)
- [Symfony Testing](https://symfony.com/doc/current/testing.html)
