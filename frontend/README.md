# Family Plan Frontend

React-based Single Page Application (SPA) for the Family Plan task management system.

## Overview

This is a standalone React application that communicates with the Family Plan backend API. The frontend is completely separated from the backend and can be deployed as an independent container.

## Technology Stack

- React 18.2
- Webpack 5
- Babel
- CSS

## Development

### Prerequisites

- Node.js 20+
- npm

### Setup

1. Install dependencies:
```bash
npm install
```

2. Configure environment:
```bash
cp .env.example .env
# Edit .env and set REACT_APP_API_URL to your backend API URL
```

3. Start development server:
```bash
npm start
```

The application will be available at http://localhost:3000 with hot reload enabled.

### Available Scripts

- `npm start` - Start development server with hot reload
- `npm run dev` - Build in development mode
- `npm run build` - Build for production
- `npm run watch` - Watch mode for development
- `npm test` - Run all Playwright tests
- `npm run test:headed` - Run tests with browser visible
- `npm run test:ui` - Run tests with Playwright UI mode
- `npm run test:debug` - Run tests in debug mode
- `npm run test:chromium` - Run tests only in Chromium
- `npm run test:firefox` - Run tests only in Firefox
- `npm run test:webkit` - Run tests only in WebKit/Safari
- `npm run test:report` - Show HTML test report

## Testing

This project uses Playwright for end-to-end testing of the frontend application.

### Running Tests

```bash
# Run all tests
npm test

# Run tests with browser visible
npm run test:headed

# Run tests in interactive UI mode
npm run test:ui

# Run tests in a specific browser
npm run test:chromium
npm run test:firefox
npm run test:webkit

# Debug tests
npm run test:debug
```

### Test Structure

Tests are located in `tests/e2e/` directory:

- `login.spec.js` - Login page and authentication flow tests
- `task-list.spec.js` - Task list display and filtering tests
- `task-creation.spec.js` - Task creation form tests
- `task-actions.spec.js` - Task completion and approval tests
- `logout.spec.js` - Logout and session management tests
- `fixtures.js` - Test fixtures and helper functions

### Writing Tests

Tests use Playwright's testing framework with fixtures for mock API responses:

```javascript
const { test, expect } = require('@playwright/test');
const { setupAuthenticatedSession } = require('./fixtures');

test.describe('My Feature', () => {
  test.beforeEach(async ({ page }) => {
    await setupAuthenticatedSession(page, 'user');
    await page.goto('/');
  });

  test('should test something', async ({ page }) => {
    // Your test code
  });
});
```

### Test Configuration

Playwright configuration is in `playwright.config.js`. Key settings:

- Tests run in Chromium, Firefox, and WebKit browsers
- Base URL: http://localhost:3000 (configurable via BASE_URL env var)
- Test timeout: 30 seconds
- Automatic test server startup
- **Screenshots captured for all tests**
- **Videos recorded for all tests**
- HTML reporter for test results
- Trace files captured on retry failures

### Test Artifacts

After running tests, the following artifacts are generated:

- **Screenshots**: `test-results/[test-name]/test-finished-1.png` - Screenshot after each test
- **Videos**: `test-results/[test-name]/video.webm` - Video recording of each test
- **HTML Report**: `playwright-report/index.html` - Interactive test report with screenshots
- **Traces**: `test-results/[test-name]/trace.zip` - Detailed trace for debugging failures

View the HTML report:
```bash
npm run test:report
```

View a specific trace:
```bash
npx playwright show-trace test-results/[test-name]/trace.zip
```

### CI/CD Integration

Tests are configured to run in CI with:
- No retries in development, 2 retries in CI
- Serial execution in CI for stability
- HTML report generation
- **Screenshot and video artifacts uploaded for all tests**
- **Test artifacts retained for 30 days**
- Trace files uploaded on failures for debugging

## Docker Development

Build and run with Docker:

```bash
# Development
docker build -t family-plan-frontend:dev -f Dockerfile .
docker run -p 3000:3000 -v $(pwd):/app family-plan-frontend:dev

# Production
docker build -t family-plan-frontend:prod -f Dockerfile.prod .
docker run -p 8080:80 family-plan-frontend:prod
```

## Docker Compose

Use with the main docker-compose setup:

```bash
# Development
docker compose up frontend

# Production
docker compose -f compose.yaml -f compose.prod.yaml up frontend
```

## Configuration

### Environment Variables

- `REACT_APP_API_URL` - Backend API base URL (default: http://localhost:8080)

### API Proxy

In development mode, the webpack dev server proxies `/api` requests to the backend API configured via `API_URL` environment variable.

## Building for Production

```bash
npm run build
```

This creates an optimized production build in the `dist/` directory with:
- Minified JavaScript and CSS
- Source maps for debugging
- Cache-busting file names
- Code splitting for optimal loading

## Deployment

The production Docker image uses nginx to serve the static files:

1. Build the production image:
```bash
docker build -t family-plan-frontend:latest -f Dockerfile.prod .
```

2. Run the container:
```bash
docker run -p 80:80 family-plan-frontend:latest
```

## Features

- User authentication and session management
- Task list view with filtering
- Real-time task updates
- Responsive design
- Error handling and loading states

## API Integration

The frontend communicates with the backend REST API:

- `POST /api/auth/login` - User authentication
- `POST /api/auth/logout` - User logout
- `GET /api/auth/me` - Get current user
- `GET /api/tasks` - Get tasks list
- `POST /api/tasks` - Create new task
- `PUT /api/tasks/{id}` - Update task
- `DELETE /api/tasks/{id}` - Delete task

See the main API documentation for complete endpoint details.

## Project Structure

```
frontend/
├── public/          # Static files
│   └── index.html   # HTML template
├── src/             # Source code
│   ├── App.jsx      # Main application component
│   ├── index.jsx    # Application entry point
│   ├── pages/       # Page components
│   ├── services/    # API client and services
│   └── styles/      # CSS styles
├── dist/            # Production build output
├── Dockerfile       # Development Docker image
├── Dockerfile.prod  # Production Docker image
├── nginx.conf       # Nginx configuration for production
├── webpack.config.js # Webpack configuration
└── package.json     # Dependencies and scripts
```

## Contributing

When making changes:

1. Follow React best practices
2. Use functional components with hooks
3. Keep components small and focused
4. Add proper error handling
5. Test with the backend API

## License

UNLICENSED - Private project
