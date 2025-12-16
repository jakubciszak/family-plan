# Playwright End-to-End Tests

This directory contains end-to-end tests for the Family Plan frontend application using Playwright.

## Test Files

- **`login.spec.js`** - Login page and authentication tests
  - Form rendering and validation
  - Successful login flow
  - Error handling for invalid credentials
  - Accessibility features

- **`task-list.spec.js`** - Task list display and interaction tests
  - Loading states
  - Empty state rendering
  - Task display with proper metadata
  - Role-based button visibility (user vs admin)

- **`task-creation.spec.js`** - Task creation form tests
  - Form display and toggle
  - Field validation
  - Input types and constraints
  - Successful task creation
  - Form state management

- **`task-actions.spec.js`** - Task action tests
  - Task completion workflow
  - Task approval workflow (admin only)
  - Proper API request payloads
  - UI state updates after actions

- **`logout.spec.js`** - Logout and session management tests
  - Logout button display
  - Successful logout flow
  - State clearing after logout
  - Session persistence and expiration

- **`fixtures.js`** - Test fixtures and helper functions
  - Mock API responses
  - Test credentials
  - Helper functions for session setup

## Running Tests

```bash
# Run all tests
npm test

# Run tests in headed mode (see browser)
npm run test:headed

# Run tests with UI mode (interactive)
npm run test:ui

# Run tests in a specific browser
npm run test:chromium
npm run test:firefox
npm run test:webkit

# Debug tests
npm run test:debug

# View test report
npm run test:report
```

## Test Structure

Each test file follows this pattern:

```javascript
const { test, expect } = require('@playwright/test');
const { mockApiResponses, setupAuthenticatedSession } = require('./fixtures');

test.describe('Feature Name', () => {
  test.beforeEach(async ({ page }) => {
    // Setup code
  });

  test('should do something', async ({ page }) => {
    // Test code
  });
});
```

## Mock API Responses

Tests use mocked API responses to avoid dependencies on the backend:

- **Authentication**: Mock login, logout, and user session endpoints
- **Tasks**: Mock task list, creation, completion, and approval endpoints
- **State Management**: Tests track mock state changes to verify UI updates

## Best Practices

1. **Use descriptive test names** - Make it clear what the test validates
2. **Setup mocks before navigation** - Ensure mocks are in place before page loads
3. **Wait for specific elements** - Use `waitForSelector` instead of arbitrary timeouts
4. **Test user behavior** - Simulate real user interactions
5. **Verify both happy and error paths** - Test success and failure scenarios
6. **Keep tests isolated** - Each test should be independent

## Configuration

See `playwright.config.js` for:
- Browser configurations
- Timeout settings
- Test server setup
- Reporter options
- Screenshot and video settings

## Troubleshooting

### Tests timing out
- Increase timeout in `playwright.config.js`
- Check that mocks are set up before navigation
- Ensure the dev server is starting correctly

### Tests failing intermittently
- Check for race conditions in async operations
- Use proper wait strategies instead of arbitrary timeouts
- Verify mock responses match expected format

### Dev server conflicts
- Ensure only one test run at a time
- Use `reuseExistingServer: true` for local development
- Check that port 3000 is available

## CI/CD Integration

Tests are configured to run in CI with:
- Automatic retries on failure (2 retries in CI)
- Serial execution for stability
- HTML report generation
- Screenshot and video capture on failures

Environment variable `CI=true` enables CI-specific behavior.
