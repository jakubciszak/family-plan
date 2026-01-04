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

This project uses Playwright for end-to-end testing.

### E2E Tests (`tests/e2e/`)

Tests with mocked API responses that run on all branches.

```bash
# Run E2E tests (default)
npm test

# Run with browser visible
npm run test:headed

# Run in interactive UI mode
npm run test:ui

# Run in a specific browser
npm run test:chromium
npm run test:firefox
npm run test:webkit

# Debug tests
npm run test:debug
```

**Test files:**
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

The GitHub Actions workflow runs E2E tests on every push and pull request:

- Fast execution (~6 seconds)
- No external dependencies
- Provides immediate feedback
- 2 retries on failure
- HTML report generation
- Screenshot and video artifacts uploaded for all tests
- Test artifacts retained for 30 days
- Trace files uploaded on failures for debugging

## Docker Development

### Using Docker Compose (Recommended)

The easiest way to run the frontend in development mode with hot reload:

```bash
# From the repository root
docker compose up -d

# The frontend will be available at http://localhost:3000
# with hot reload enabled
```

The Docker Compose setup includes:
- Automatic dependency installation
- Hot Module Replacement (HMR)
- File watching with polling (works on all platforms)
- API proxy to backend service

### Manual Docker Build

Build and run the development container manually:

```bash
# Development
docker build -t family-plan-frontend:dev -f Dockerfile .
docker run -p 3000:3000 -v $(pwd):/app family-plan-frontend:dev

# Production
docker build -t family-plan-frontend:prod -f Dockerfile.prod .
docker run -p 8080:80 family-plan-frontend:prod
```

## Standalone Development

Run the frontend without Docker:

```bash
# Install dependencies
npm install

# Configure API URL
cp .env.example .env
# Edit .env and set REACT_APP_API_URL=http://localhost:8080

# Start development server
npm start
```

The application will be available at http://localhost:3000 with hot reload.

**Note:** You need the backend API running (either via Docker or standalone) for the frontend to work.

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
- **Multi-language support** (English and Polish)
  - Language switcher in header
  - Automatic browser language detection
  - Persistent language selection (localStorage)
  - Easy to add new languages

## Internationalization (i18n)

The frontend uses **react-i18next** for multi-language support.

### Supported Languages

- Polish (pl) - Default
- English (en)

### Language Switching

Users can switch languages using the language switcher in the application header. The selected language is stored in the browser's localStorage for persistence across sessions.

### Translation Files

Translation files are located in `src/i18n/locales/`:
- `en.json` - English translations
- `pl.json` - Polish translations

### Using Translations in Components

```javascript
import { useTranslation } from 'react-i18next';

function MyComponent() {
  const { t } = useTranslation();
  
  return (
    <div>
      <h1>{t('app.title')}</h1>
      <p>{t('app.welcome', { name: userName })}</p>
    </div>
  );
}
```

### Adding New Languages

1. Create a new translation file in `src/i18n/locales/` (e.g., `de.json`)
2. Copy the structure from `en.json` and translate the values
3. Import the translations in `src/i18n/config.js`:
   ```javascript
   import deTranslations from './locales/de.json';
   ```
4. Add the language to the i18n configuration:
   ```javascript
   resources: {
     en: { translation: enTranslations },
     pl: { translation: plTranslations },
     de: { translation: deTranslations }
   }
   ```
5. Update `src/components/LanguageSwitcher.jsx` to include the new language button

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
│   ├── components/  # Reusable components
│   │   └── LanguageSwitcher.jsx  # Language switcher component
│   ├── i18n/        # Internationalization
│   │   ├── config.js             # i18n configuration
│   │   └── locales/              # Translation files
│   │       ├── en.json           # English translations
│   │       └── pl.json           # Polish translations
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
