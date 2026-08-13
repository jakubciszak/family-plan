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

- **`task-assignment.spec.js`** - Task assignment tests
  - Assignment to users
  - Assignment visibility and permissions
  - Assignment workflow

- **`user-points.spec.js`** - User points display tests
  - Points balance display
  - Points updates after actions

- **`bonus-rules.spec.js`** - Bonus points rules management tests (NEW)
  - Access control (admin-only features)
  - Navigation between pages
  - Displaying bonus rules with status indicators
  - Creating new rules (consecutive days and monthly task count)
  - Editing existing rules
  - Activating and deactivating rules
  - Form validation and error handling
  - Responsive design and user experience

- **`logout.spec.js`** - Logout and session management tests
  - Logout button display
  - Successful logout flow
  - State clearing after logout
  - Session persistence and expiration

- **`fixtures.js`** - Test fixtures and helper functions
  - Mock API responses (including bonus rules)
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
- **Bonus Rules**: Mock bonus rules list, creation, update, activate/deactivate endpoints
- **State Management**: Tests track mock state changes to verify UI updates

## Best Practices

1. **Use descriptive test names** - Make it clear what the test validates
2. **Setup mocks before navigation** - Ensure mocks are in place before page loads
3. **Wait for specific elements** - Use `waitForSelector` instead of arbitrary timeouts
4. **Test user behavior** - Simulate real user interactions
5. **Verify both happy and error paths** - Test success and failure scenarios
6. **Keep tests isolated** - Each test should be independent

## Test Artifacts

Playwright automatically captures the following artifacts during test execution:

### Screenshots
- Captured on failure
- Location: `test-results/[test-name]/test-finished-1.png`
- Useful for visual verification of test states
- Automatically uploaded in CI pipeline

### Videos
- Recorded for all tests
- Location: `test-results/[test-name]/video.webm`
- Shows complete test execution flow
- Helps debug test failures

### Traces
- Captured for every test in CI and on retry locally
- Location: `test-results/[test-name]/trace.zip`
- Contains detailed execution information
- View with: `npx playwright show-trace path/to/trace.zip`

### HTML Report
- Interactive test results dashboard
- Location: `playwright-report/index.html`
- Includes screenshots and test timings
- View with: `npm run test:report`

### Readable artifact bundle
- Location: `playwright-artifacts-readable/index.html`
- Groups screenshots, videos and trace ZIP files by test
- Shows screenshots inline after downloading the artifact
- Generate locally with: `npm run test:artifacts`

## Configuration

See `playwright.config.js` for:
- Browser configurations
- Timeout settings
- Test server setup
- Reporter options
- Screenshot and video settings (enabled for all tests)
- Trace capture settings

## Troubleshooting

### Tests timing out
- Increase timeout in `playwright.config.js`
- Check that mocks are set up before navigation
- Ensure the dev server is starting correctly

### Tests failing intermittently
- Check for race conditions in async operations
- Use proper wait strategies instead of arbitrary timeouts
- Verify mock responses match expected format
- Review video recordings to see what happened

### Viewing test artifacts
- Check `test-results/` directory for screenshots and videos
- Use `npm run test:report` to view HTML report with screenshots
- Use `npm run test:artifacts` to build a human-readable artifact bundle
- Use `npx playwright show-trace [trace-file]` for detailed debugging

### Dev server conflicts
- Ensure only one test run at a time
- Use `reuseExistingServer: true` for local development
- Check that port 3000 is available

## CI/CD Integration

Tests are configured to run in CI with:
- Automatic retries on failure (2 retries in CI)
- Serial execution for stability
- HTML report generation
- **Human-readable artifact bundle generated for every run**
- **All test artifacts uploaded and retained for 30 days**
- Trace files captured for every CI test for debugging

### CI Artifacts

The GitHub Actions workflow automatically uploads:
1. **playwright-report** - HTML test report with screenshots (always uploaded)
2. **playwright-artifacts-readable** - Human-readable index with screenshots, videos and trace ZIP links (always uploaded)
3. **playwright-test-results** - Raw Playwright output files (always uploaded)
4. **playwright-traces** - Raw trace ZIP files (always uploaded)

Access artifacts from the Actions tab in GitHub after the workflow completes.

Environment variable `CI=true` enables CI-specific behavior.
