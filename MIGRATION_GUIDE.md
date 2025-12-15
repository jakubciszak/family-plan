# Frontend/Backend Separation Migration Guide

This document describes the refactoring to separate the frontend (React) and backend (REST API).

## Overview

The application has been refactored to follow a modern architecture:
- **Backend**: REST API built with Symfony (PHP 8.5)
- **Frontend**: React Single Page Application
- **Docker**: Complete containerization for development and production
- **API Docs**: OpenAPI/Swagger documentation

## Quick Start

### Using Docker (Recommended)

```bash
# Start all services
docker compose up

# Access points:
# - Main app: http://localhost:8080
# - React SPA: http://localhost:8080/app
# - API: http://localhost:8080/api/*
# - API Docs: http://localhost:8080/api-docs.html

# For production build:
docker compose -f compose.yaml -f compose.prod.yaml up
```

### Without Docker

```bash
# Install dependencies
composer install
npm install

# Build frontend
npm run build

# Start server
symfony server:start
```

## Architecture Changes

### Backend: REST API

#### API Controllers

Three new API controller classes have been created in `src/Presentation/Api/`:

1. **TaskApiController** (`/api/tasks`)
   - `GET /api/tasks` - List all tasks
   - `POST /api/tasks` - Create a new task
   - `GET /api/tasks/{id}` - Get a specific task
   - `POST /api/tasks/{id}/complete` - Mark task as completed
   - `POST /api/tasks/{id}/approve` - Approve a completed task (admin only)

2. **UserApiController** (`/api/users`)
   - `GET /api/users` - List all users
   - `POST /api/users` - Create a new user
   - `GET /api/users/{id}` - Get a specific user

3. **AuthApiController** (`/api/auth`)
   - `POST /api/auth/login` - Login
   - `GET /api/auth/me` - Get current authenticated user
   - `POST /api/auth/logout` - Logout

#### API Documentation

Interactive API documentation is available via OpenAPI/Swagger:

- **Swagger UI**: http://localhost:8080/api-docs.html
- **OpenAPI Spec**: http://localhost:8080/openapi.yaml

Features:
- Complete endpoint documentation
- Interactive "Try it out" functionality
- Request/response examples
- Schema definitions
- Authentication testing

See `OPENAPI_DOCUMENTATION.md` for detailed information.

#### API Response Format

All API endpoints return JSON responses in a consistent format:

```json
{
  "id": "uuid",
  "name": "string",
  "...": "other fields"
}
```

Error responses:
```json
{
  "error": "Error message"
}
```

### Frontend: React Application

#### Structure

```
assets/react/
├── App.jsx                 # Main application component
├── index.jsx              # Entry point
├── pages/
│   ├── Login.jsx          # Login page
│   └── TaskList.jsx       # Task list and management
├── services/
│   └── apiClient.js       # API communication service
└── styles/
    └── app.css            # Application styles
```

#### Key Components

1. **App.jsx** - Main application component
   - Manages authentication state
   - Routes between Login and TaskList
   - Handles user session

2. **Login.jsx** - Authentication page
   - Email/password form
   - Calls `/api/auth/login`
   - Redirects to task list on success

3. **TaskList.jsx** - Task management
   - Displays all tasks
   - Create new tasks
   - Complete/Approve tasks
   - Real-time updates via API

4. **apiClient.js** - API communication service
   - Centralized HTTP methods (GET, POST, PUT, DELETE)
   - JSON request/response handling
   - Error handling

## Setup Instructions

### Prerequisites

- PHP 8.5+
- Node.js 18+
- Composer 2.x
- Docker (recommended)

### Installation

#### Using Docker (Recommended)

```bash
# 1. Start all services (PHP, Nginx, PostgreSQL, Node/React)
docker compose up -d

# 2. Install PHP dependencies
docker compose run --rm php composer install

# 3. Run database migrations
docker compose exec php php bin/console doctrine:migrations:migrate

# 4. Create super admin
docker compose exec php php bin/console app:create-super-admin

# 5. Access the application
# - Main app: http://localhost:8080
# - React SPA: http://localhost:8080/app
# - API: http://localhost:8080/api/*
# - API Docs: http://localhost:8080/api-docs.html
```

The Docker setup includes:
- **php**: PHP 8.5-fpm with Composer
- **nginx**: Web server
- **database**: PostgreSQL 16
- **node**: Node.js 20 for asset compilation (watch mode)
- **react-dev**: Dedicated React development server with hot reload

For production deployment:
```bash
# Build and run production services
docker compose -f compose.yaml -f compose.prod.yaml up --build

# React production build is served by Nginx on port 3001
```

See `docker/react/README.md` for detailed Docker configuration information.

#### Without Docker

1. **Install PHP dependencies:**
   ```bash
   # Requires PHP 8.5 locally
   composer install
   ```

2. **Install Node.js dependencies:**
   ```bash
   npm install
   
   # Install React dependencies
   npm install --save react react-dom
   npm install --save-dev @babel/preset-react
   ```

3. **Build frontend assets:**
   ```bash
   # Development build
   npm run dev
   
   # Production build
   npm run build
   
   # Watch mode (auto-rebuild on changes)
   npm run watch
   ```

4. **Setup database:**
   ```bash
   php bin/console doctrine:migrations:migrate
   php bin/console app:create-super-admin
   ```

5. **Start the application:**
   ```bash
   # Using Symfony CLI
   symfony server:start
   ```

6. **Access the application:**
   - Legacy Twig interface: `http://localhost:8080/`
   - New React SPA: `http://localhost:8080/app`
   - API endpoints: `http://localhost:8080/api/*`

## Testing

### Backend API Tests

API tests are located in `tests/Api/` and use PHPUnit with Symfony WebTestCase:

```bash
# Run all API tests
docker compose run --rm php vendor/bin/phpunit tests/Api/

# Run specific test class
docker compose run --rm php vendor/bin/phpunit tests/Api/TaskApiTest.php
```

Test classes:
- `TaskApiTest.php` - Tests for task management endpoints
- `UserApiTest.php` - Tests for user management endpoints
- `ApiTestCase.php` - Base class with helper methods

### Frontend E2E Tests

Frontend end-to-end tests would use Playwright or Cypress (to be implemented):

```bash
# Placeholder for E2E tests
# npm run test:e2e
```

## Migration Path

### Phase 1: Dual Mode (Current)
- Both Twig templates and React app are available
- Old URLs (`/tasks`, `/users`) use Twig
- New URL (`/app`) uses React SPA
- APIs available at `/api/*`

### Phase 2: React Only (Future)
1. Redirect root `/` to `/app`
2. Remove old Twig controllers
3. Keep only error templates in Twig
4. API becomes the only backend interface

## API Usage Examples

### Authentication

```javascript
// Login
const response = await fetch('/api/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'admin@familyplan.local',
    password: 'admin123'
  })
});

// Get current user
const user = await fetch('/api/auth/me').then(r => r.json());
```

### Task Management

```javascript
// Get all tasks
const tasks = await fetch('/api/tasks')
  .then(r => r.json());

// Create a task
await fetch('/api/tasks', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    name: 'Clean the kitchen',
    description: 'Wash dishes and mop floor',
    points: 50,
    frequency: 'daily'
  })
});

// Complete a task
await fetch(`/api/tasks/${taskId}/complete`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ userId: currentUserId })
});
```

## Configuration

### CORS (if deploying frontend separately)

Add CORS configuration in `config/packages/nelmio_cors.yaml`:

```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['*']
        allow_methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
        allow_headers: ['Content-Type', 'Authorization']
        max_age: 3600
    paths:
        '^/api/': ~
```

### Security for API

Update `config/packages/security.yaml` to handle API authentication:

```yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: false
            json_login:
                check_path: /api/auth/login
                username_path: email
                password_path: password
```

## Next Steps

1. **Complete React dependencies installation** - Currently blocked by permission issues, needs proper npm setup
2. **Add CORS bundle** if deploying frontend separately
3. **Implement frontend E2E tests** with Playwright or Cypress
4. **Add API rate limiting** for production
5. **Implement proper authentication** (JWT or session-based)
6. **Add frontend routing** with React Router for multi-page navigation
7. **Progressive migration** of remaining Twig pages to React

## Notes

- The old Twig-based interface is still functional during the migration
- API controllers maintain the same business logic as the original controllers
- Domain layer remains unchanged (DDD/Hexagonal Architecture preserved)
- All existing PHPUnit tests for domain and application layers still work

## Troubleshooting

### npm Permission Errors

If you encounter permission errors with npm:
```bash
# Clean node_modules and reinstall
rm -rf node_modules package-lock.json
npm install
```

### Docker PHP Dependencies

If composer fails locally due to PHP version:
```bash
# Always use Docker for PHP operations
docker compose run --rm php composer install
docker compose run --rm php vendor/bin/phpunit
```

### API 404 Errors

Ensure controllers are in the correct namespace and routes are properly configured:
```bash
php bin/console debug:router | grep api
```
