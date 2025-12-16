# Playwright Integration Tests

This directory contains integration tests that run against the **real backend API** (not mocked). These tests verify the complete functionality of the application with actual API calls.

## Difference from E2E Tests

- **E2E Tests (`tests/e2e/`)**: Use mocked API responses, run fast, can run anywhere
- **Integration Tests (`tests/integration/`)**: Use real backend API, require full stack running

## Prerequisites

Before running integration tests, you must have the full application stack running:

```bash
# From the root directory of the project
docker-compose up -d

# Wait for services to be ready
# Database, backend API, and frontend should all be running
```

Verify services are running:
- Backend API: http://localhost:8080
- Frontend: http://localhost:3000
- Database: PostgreSQL on port 5432

## Running Integration Tests

```bash
# Run integration tests
npm run test:integration

# Run with headed mode (see browser)
npm run test:integration:headed

# Run with debug mode
npm run test:integration:debug
```

## Test Files

- **`login.spec.js`** - Login functionality with real API authentication
- **`task-flow.spec.js`** - Complete task workflows including create, complete, and approve

## Test Data

Integration tests use the default super admin account created by the backend:
- Email: `admin@familyplan.local` (or `SUPER_ADMIN_EMAIL` env var)
- Password: `admin123` (or `SUPER_ADMIN_PASSWORD` env var)

Make sure this user exists in the database before running tests.

## Configuration

Integration test configuration is in `playwright.integration.config.js`:
- Longer timeouts (60s) for real API calls
- Sequential execution (not parallel) to avoid data conflicts
- No webServer (expects services to be already running)
- Screenshots and videos captured for all tests

## CI/CD

Integration tests are configured to run:
- **On main branch only** - via GitHub Actions workflow
- After mocked tests pass
- With full docker-compose stack

## Debugging Integration Tests

If tests fail:

1. Check that all services are running:
   ```bash
   docker-compose ps
   ```

2. Check backend API logs:
   ```bash
   docker-compose logs php
   ```

3. Check database connection:
   ```bash
   docker-compose logs database
   ```

4. Verify frontend can reach backend:
   ```bash
   curl http://localhost:8080/api/auth/me
   ```

5. Check test artifacts in `test-results/` directory for screenshots and videos

## Best Practices

1. **Clean state**: Tests should be idempotent and not depend on specific data
2. **Unique identifiers**: Use timestamps or UUIDs when creating test data
3. **Cleanup**: Consider cleaning up test data after tests (future improvement)
4. **Wait strategies**: Use explicit waits for API responses
5. **Error handling**: Tests should handle slow API responses gracefully

## Adding New Tests

When adding new integration tests:

1. Create a new `.spec.js` file in this directory
2. Use the login helper function for authenticated tests
3. Use unique identifiers for any data you create
4. Add appropriate waits for API responses
5. Verify the test runs successfully with `docker-compose up`

Example:
```javascript
const { test, expect } = require('@playwright/test');
const { login, getAdminCredentials } = require('./helpers');

test('my integration test', async ({ page }) => {
  const { email, password } = getAdminCredentials();
  await login(page, email, password);
  // Your test code here
});
```
