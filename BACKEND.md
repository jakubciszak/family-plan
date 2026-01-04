# Backend Directory Structure

This directory contains the Family Plan backend API application built with Symfony.

## Overview

The backend is a REST API that provides all business logic and data management for the Family Plan application. It follows Hexagonal Architecture (Ports and Adapters) with Domain-Driven Design principles.

## Key Directories

- `src/` - Application source code
  - `TaskManagement/` - Task management bounded context
  - `UserManagement/` - User management bounded context
  - `Shared/` - Shared kernel
  - `Presentation/` - Controllers and API endpoints
- `config/` - Symfony configuration files
- `migrations/` - Database migrations
- `tests/` - PHPUnit tests
- `bin/` - Executable scripts
- `public/` - Web server document root (entry point)
- `templates/` - Twig templates (minimal, mostly for traditional routes)
- `docker/` - Docker configuration for backend services

## Frontend Separation

The frontend has been moved to a separate `frontend/` directory at the root level and is now a completely independent application. The backend serves only as an API provider.

### Removed from Backend

- React application code (was in `assets/react/`)
- Frontend build process (Webpack/Encore for React)
- React-specific controllers and routes
- Frontend asset serving (except static files in `public/`)

### Backend Responsibilities

- REST API endpoints (`/api/*`)
- Authentication and authorization
- Business logic and domain models
- Database operations
- Data validation and processing

## API Endpoints

All API endpoints are prefixed with `/api/`:

- `/api/auth/*` - Authentication endpoints
- `/api/users/*` - User management
- `/api/tasks/*` - Task management
- `/api/task-templates/*` - Task template management

See `public/openapi.yaml` for complete API documentation.

## Development

The backend can be run independently of the frontend using Docker:

### Docker Development (Recommended)

```bash
# Start backend services (database + PHP + nginx)
docker compose up -d database php nginx

# Initialize database (first time only)
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:create-super-admin

# Install dependencies (first time only)
docker compose exec php composer install

# The API will be available at http://localhost:8080/api
# API Documentation: http://localhost:8080/api-docs.html
```

### Standalone Development

```bash
# Install dependencies
composer install

# Configure environment
cp .env .env.local
# Edit .env.local with your PostgreSQL credentials

# Run migrations
php bin/console doctrine:migrations:migrate

# Create super admin
php bin/console app:create-super-admin

# Start server
symfony serve
# OR
php -S localhost:8080 -t public
```

### Common Development Tasks

```bash
# Docker
docker compose exec php php bin/console cache:clear
docker compose exec php php bin/console doctrine:migrations:diff
docker compose exec php composer require vendor/package
docker compose exec php php bin/phpunit

# Standalone
php bin/console cache:clear
php bin/console doctrine:migrations:diff
composer require vendor/package
php bin/phpunit
```

## Architecture

The application follows Hexagonal Architecture with CQRS:

- **Domain Layer**: Core business logic and entities
- **Application Layer**: Use cases, commands, queries, handlers
- **Infrastructure Layer**: Database, external services
- **Presentation Layer**: API controllers, request/response handling

## Testing

### Running Tests with Docker

```bash
# Run all tests
docker compose exec php php bin/phpunit

# Run specific test suite
docker compose exec php php bin/phpunit tests/Unit
docker compose exec php php bin/phpunit tests/Integration

# Run specific test file
docker compose exec php php bin/phpunit tests/Unit/Domain/ValueObject/EmailTest.php

# Run with coverage (requires Xdebug)
docker compose exec php php bin/phpunit --coverage-html coverage
```

### Running Tests Standalone

```bash
# Run all tests
php bin/phpunit

# Run specific test
php bin/phpunit tests/Unit/Domain/ValueObject/EmailTest.php

# Generate coverage report
php bin/phpunit --coverage-html coverage
```

### Test Structure

- `tests/Unit/` - Unit tests for domain layer
- `tests/Integration/` - Integration tests for infrastructure
- `tests/Api/` - API functional tests
- `features/` - Behat acceptance tests (BDD)

## Configuration

Backend configuration is in `.env` files:
- `.env` - Default configuration
- `.env.dev` - Development environment
- `.env.prod` - Production environment
- `.env.test` - Test environment

## Docker Services

When using Docker Compose, the backend uses these services:

### Core Backend Services
- **`database`** - PostgreSQL 16 database
  - Port: 5432 (exposed in dev with compose.override.yaml)
  - Data persisted in Docker volume `database_data`
  - Health check enabled

- **`php`** - PHP 8.3+ FPM application server
  - Runs Symfony application
  - Source code mounted from `.` to `/app`
  - Auto-reload on file changes
  - Composer available in container

- **`nginx`** - Nginx web server
  - Port: 8080 (host) → 80 (container)
  - Serves API endpoints
  - Proxies PHP requests to `php` service
  - Configuration in `docker/nginx/default.conf`

### Development Support Services
- **`mailer`** (via compose.override.yaml) - Mailpit email testing
  - Web UI: http://localhost:8025
  - SMTP: localhost:1025
  - Catches all emails sent by the application

### Frontend Service (Optional)
- **`frontend`** - React SPA (can be run separately)
  - Port: 3000
  - See [frontend/README.md](../frontend/README.md) for details

**Note:** The `node` and `react-dev` services in compose.yaml are for legacy Webpack Encore assets and can be ignored for API-only development.
