# Behat E2E Tests - Compatibility Note

## Current Status

**Behat tests are temporarily disabled** in the CI pipeline due to incompatibility with Symfony 8.0.

## Technical Details

- **Project Requirements**: Symfony 8.0.*, PHP 8.5
- **Behat Support**: Behat 3.x currently supports Symfony ^5.4 || ^6.4 || ^7.0
- **Behat 4.x Status**: In development, not yet stable

## What's Ready

All Behat test infrastructure has been prepared and is ready to use:

- **Configuration**: `behat.yml` configured for API testing
- **Context Classes**: `tests/Behat/ApiContext.php` with step definitions
- **Feature Files**: 
  - `features/api/task_management.feature` - Task API scenarios
  - `features/api/user_management.feature` - User API scenarios

## When Will Behat Work?

Behat tests will be enabled once either:
1. Behat 4.x is released with Symfony 8.0 support
2. The project is downgraded to Symfony 7.x (not recommended)

## Alternative: PHPUnit API Tests

The project currently uses PHPUnit for API testing (`tests/Api/`), which provides equivalent E2E test coverage:
- `tests/Api/TaskApiTest.php`
- `tests/Api/UserApiTest.php`
- `tests/Api/ApiTestCase.php`

These tests are running successfully in the CI pipeline.

## Running Behat Tests Locally (When Compatible)

Once Behat compatibility is restored, you can run tests with:

```bash
# Install dependencies
docker compose exec php composer install

# Run all Behat tests
docker compose exec php vendor/bin/behat

# Run specific feature
docker compose exec php vendor/bin/behat features/api/task_management.feature

# Run with strict mode
docker compose exec php vendor/bin/behat --strict
```

## References

- [Behat Issue #1428](https://github.com/Behat/Behat/issues/1428) - Symfony 8.0 support
- [FriendsOfBehat/SymfonyExtension](https://github.com/FriendsOfBehat/SymfonyExtension) - Symfony integration
