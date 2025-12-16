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

The backend can be run independently of the frontend:

```bash
# Start backend services only
docker compose up database php nginx

# The API will be available at http://localhost:8080/api
```

## Architecture

The application follows Hexagonal Architecture with CQRS:

- **Domain Layer**: Core business logic and entities
- **Application Layer**: Use cases, commands, queries, handlers
- **Infrastructure Layer**: Database, external services
- **Presentation Layer**: API controllers, request/response handling

## Testing

Run backend tests:

```bash
docker run --rm -v $(pwd):/app -w /app php:8.5-cli vendor/bin/phpunit
```

## Configuration

Backend configuration is in `.env` files:
- `.env` - Default configuration
- `.env.dev` - Development environment
- `.env.prod` - Production environment
- `.env.test` - Test environment

## Docker Services

Backend-specific services:
- `database` - PostgreSQL database
- `php` - PHP-FPM application server
- `nginx` - Web server for API

The `frontend` service is now separate and optional.
