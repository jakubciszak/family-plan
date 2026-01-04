# 👨‍👩‍👧‍👦 Family Plan

A modern application for organizing family house works with children, built with **separated frontend, mobile, and backend architecture**.

## 🏗️ Architecture Overview

Family Plan consists of three independent applications:

### 🎨 Frontend (React SPA)
- **Location**: `/frontend` directory
- **Technology**: React 18, Webpack 5
- **Deployment**: Standalone container with Nginx
- **Communication**: REST API calls to backend
- **Port**: 3000 (development), 80 (production container)

### 📱 Mobile (React Native)
- **Location**: `/mobile` directory
- **Technology**: React Native 0.76, TypeScript
- **Authentication**: JWT-based authentication
- **Platforms**: iOS and Android
- **Communication**: REST API calls to backend

### ⚙️ Backend (Symfony API)
- **Location**: Root directory (main repository)
- **Technology**: Symfony 7.1, PHP 8.3+
- **Architecture**: Hexagonal Architecture with DDD and CQRS
- **API**: RESTful endpoints under `/api/*`
- **Port**: 8080 (development), exposed via Nginx

See detailed documentation:
- [Frontend README](frontend/README.md) - React application setup and development
- [Mobile README](mobile/README.md) - React Native mobile app setup and development
- [Backend Documentation](BACKEND.md) - Symfony API architecture and structure

## 🚀 Features

- **Separated Architecture**: Independent frontend and backend applications
- **Multi-Language Support**: Full internationalization (i18n) with English and Polish translations
- **Multi-Platform**: Web (React SPA) and Mobile (React Native for iOS/Android)
- **Task Management**: Create tasks with points, frequency, and descriptions
- **User Management**: Create user and admin accounts with role-based permissions
- **Security & Authentication**: JWT authentication for mobile, session-based for web
- **Approval Workflow**: Admins can review and approve completed tasks
- **Points System**: Reward system with configurable points (0-1000) per task
- **Frequency-Based Tasks**: Support for Once, Daily, Weekly, and Monthly recurring tasks
- **Bonus Rules**: Configurable bonus points for consecutive days or monthly task counts
- **RESTful API**: Complete REST API for all operations

## 🌍 Multi-Language Support

Family Plan fully supports internationalization (i18n) in both frontend and backend:

### Supported Languages
- 🇵🇱 **Polish** (default)
- 🇬🇧 **English**

### Frontend (React)
- Uses **react-i18next** for translations
- Language detection from browser settings
- Language switcher in the header for easy switching between languages
- Persistent language selection (stored in localStorage)
- Translation files located in `frontend/src/i18n/locales/`

### Backend (Symfony)
- Uses **Symfony Translation** component
- Automatic locale detection from `Accept-Language` HTTP header
- Translation files located in `translations/`
- Fallback to Polish for missing translations

### Adding New Languages
To add a new language:

**Frontend:**
1. Create a new translation file in `frontend/src/i18n/locales/` (e.g., `de.json`)
2. Add the language to the i18n config in `frontend/src/i18n/config.js`
3. Update the LanguageSwitcher component to include the new language button

**Backend:**
1. Create a new translation file in `translations/` (e.g., `messages.de.yaml`)
2. Update the LocaleListener to support the new locale in `src/Shared/Infrastructure/EventListener/LocaleListener.php`

## 🏗️ Architecture

### Hexagonal Architecture (Ports & Adapters)
- **Domain Layer**: Pure business logic with no framework dependencies
- **Application Layer**: Use cases via Commands and Queries (CQRS)
- **Infrastructure Layer**: Framework integrations (Doctrine, Symfony)
- **Presentation Layer**: Controllers and views

### Domain-Driven Design
- **Bounded Contexts**: UserManagement and TaskManagement
- **Aggregates**: User and Task with rich domain models
- **Value Objects**: Email, Role, TaskName, Points, Frequency, TaskStatus, Uuid
- **Domain Events**: UserCreated, TaskCreated, TaskCompleted, TaskApproved
- **Repository Pattern**: Abstract persistence concerns
- **State Pattern**: Task state management with clear transition rules (see [docs/STATE_PATTERN.md](docs/STATE_PATTERN.md))

### CQRS
- **Commands**: CreateUser, CreateTask, CompleteTask, ApproveTask
- **Command Handlers**: Separate handlers for each command
- **Queries**: Structured for read operations

## 🛠️ Technology Stack

### Frontend
- React 18.2
- Webpack 5
- Babel
- Docker + Nginx

### Backend
- PHP 8.3+ (ready for PHP 8.4+)
- Symfony 7.1 (ready for Symfony 8 upgrade)
- Doctrine ORM 3.x
- PostgreSQL Database
- Docker + PHP-FPM + Nginx

## 📋 Requirements

- Docker & Docker Compose (recommended)
- OR for local development:
  - PHP 8.3 or higher
  - Composer 2.x
  - Node.js 20+ & npm
  - PostgreSQL 16+

## ⚙️ Environment Configuration

### Docker Development (Default)

When using Docker Compose, no manual environment configuration is needed. The default values in `compose.yaml` work out of the box:

- Frontend automatically connects to backend at `http://nginx:80` (internal Docker network)
- Backend connects to database at `database:5432` (internal Docker network)
- All services communicate via Docker's internal network

### Standalone Development

If running services outside Docker, you need to configure environment files:

**Frontend Environment** (`frontend/.env`):
```bash
# Copy example file
cd frontend
cp .env.example .env

# Edit .env
REACT_APP_API_URL=http://localhost:8080
```

**Backend Environment** (`.env.local` or `.env.dev`):
```bash
# Copy and customize
cp .env .env.local

# Key settings:
DATABASE_URL=postgresql://app:!ChangeMe!@localhost:5432/app
SUPER_ADMIN_EMAIL=admin@familyplan.local
SUPER_ADMIN_PASSWORD=admin123
APP_ENV=dev
```

**Note:** The `.env.dev`, `.env.prod`, and `.env.test` files are used by Docker containers, not for local development.

## 🚀 Quick Start with Docker (Development Mode)

The easiest way to run the complete application in development mode with hot reload:

```bash
# Clone the repository
git clone https://github.com/jakubciszak/family-plan.git
cd family-plan

# Start all services (backend + frontend + database)
docker compose up -d

# Wait for services to start (about 30 seconds)
# Check logs if needed: docker compose logs -f

# Initialize the database (first time only)
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:create-super-admin

# Install backend dependencies (first time only)
docker compose exec php composer install

# Install frontend dependencies (first time only, if not already done)
docker compose exec frontend npm install
```

**Access the application:**
- Frontend (React SPA): http://localhost:3000 (with hot reload)
- Backend API: http://localhost:8080/api
- API Documentation: http://localhost:8080/api-docs.html
- Database: localhost:5432 (PostgreSQL, exposed via compose.override.yaml)
- Mailpit (email testing): http://localhost:8025

**Default Credentials:**
- Email: `admin@familyplan.local`
- Password: `admin123`

**Development Features:**
- Frontend hot reload enabled (changes in `frontend/` directory auto-refresh)
- Backend auto-reload via volume mounts (changes in `src/` directory are immediately available)
- Database data persisted in Docker volume

**Stopping the application:**
```bash
# Stop all services
docker compose down

# Stop and remove volumes (WARNING: deletes database data)
docker compose down -v
```

## 🚀 Alternative Development Setup (Without Docker)

### Frontend Development (Standalone)

If you prefer to run the frontend outside of Docker:

```bash
cd frontend

# Install dependencies
npm install

# Configure API URL
cp .env.example .env
# Edit .env and set REACT_APP_API_URL=http://localhost:8080

# Start development server
npm start
```

Frontend will be available at http://localhost:3000 with hot reload.

**Note:** You still need to run the backend (either with Docker or standalone) for the frontend to work properly.

### Backend Development (Standalone)

If you prefer to run the backend outside of Docker:

```bash
# Install PHP dependencies
composer install

# Configure environment
cp .env .env.local
# Edit .env.local with your database credentials

# Create database and run migrations
php bin/console doctrine:migrations:migrate

# Create super admin user
php bin/console app:create-super-admin

# Start development server
symfony serve
# OR
php -S localhost:8080 -t public
```

Backend API will be available at http://localhost:8080

**Note:** You need PostgreSQL 16+ running locally for standalone backend development.

### Building Frontend Assets for Legacy Symfony Integration

**Note:** This is only needed if you're using the legacy Symfony Twig templates, not for the standalone React SPA.

The main repository still contains Webpack Encore for legacy Symfony frontend support:

```bash
# In the root directory (not frontend/)
npm install
npm run build

# For development
npm run watch
```

**For the modern React SPA**, use the commands in the `frontend/` directory instead.

## 📁 Project Structure

```
family-plan/
├── frontend/                        # Frontend React Application (STANDALONE)
│   ├── src/                         # React source code
│   │   ├── App.jsx                  # Main application component
│   │   ├── pages/                   # Page components
│   │   ├── services/                # API client
│   │   └── styles/                  # CSS styles
│   ├── public/                      # Static files
│   ├── Dockerfile                   # Development container
│   ├── Dockerfile.prod              # Production container
│   ├── webpack.config.js            # Build configuration
│   └── package.json                 # Frontend dependencies
│
├── src/                             # Backend Symfony Application
│   ├── Shared/                      # Shared Kernel
│   │   ├── Domain/                  # Shared domain concepts
│   │   └── Infrastructure/          # Shared infrastructure
│   ├── UserManagement/              # User Bounded Context
│   │   ├── Domain/                  # User domain logic
│   │   ├── Application/             # User use cases
│   │   └── Infrastructure/          # User infrastructure
│   ├── TaskManagement/              # Task Bounded Context
│   │   ├── Domain/                  # Task domain logic
│   │   ├── Application/             # Task use cases
│   │   └── Infrastructure/          # Task infrastructure
│   └── Presentation/
│       ├── Api/                     # REST API Controllers
│       └── Controller/              # Traditional Controllers (legacy)
│
├── docker/                          # Docker configurations
│   ├── php/                         # PHP-FPM container config
│   │   └── Dockerfile               # Backend PHP container
│   ├── nginx/                       # Nginx container config
│   │   └── default.conf             # Backend API nginx config
│   └── react/                       # Legacy (deprecated)
│
├── compose.yaml                     # Development docker compose
├── compose.override.yaml            # Development overrides (auto-merged)
├── compose.prod.yaml                # Production configuration
├── docker-compose.hostinger.yml     # Hostinger deployment config
└── README.md                        # This file
```

## 🔧 Development Workflow

### Docker Development (Recommended)

The recommended way to develop is using Docker Compose, which provides all services with hot reload:

```bash
# Start all services in development mode
docker compose up -d

# View logs from all services
docker compose logs -f

# View logs from specific service
docker compose logs -f frontend
docker compose logs -f php

# Execute commands in containers
docker compose exec php php bin/console cache:clear
docker compose exec php composer install
docker compose exec frontend npm install

# Rebuild containers after Dockerfile changes
docker compose up -d --build

# Stop all services
docker compose down
```

### Frontend Development (Docker)

When using Docker, the frontend container:
- Runs webpack dev server on port 3000
- Automatically reloads when you edit files in `frontend/src/`
- Hot module replacement (HMR) is enabled
- Proxies API requests to the backend container

```bash
# Restart frontend after package.json changes
docker compose restart frontend

# Install new npm package
docker compose exec frontend npm install <package-name>

# Run frontend tests
docker compose exec frontend npm test
```

### Backend Development (Docker)

When using Docker, the PHP container:
- Runs PHP-FPM with auto-reload on file changes
- Mounts your local `src/` directory
- Connects to PostgreSQL in the database container

```bash
# Run database migrations
docker compose exec php php bin/console doctrine:migrations:migrate

# Clear Symfony cache
docker compose exec php php bin/console cache:clear

# Install new composer package
docker compose exec php composer require <package-name>

# Run backend tests
docker compose exec php php bin/phpunit
```

### Standalone Development

If you prefer to run services outside Docker, see the "Alternative Development Setup" section above.

## 🐳 Docker Compose Files

The project uses different Docker Compose files for different environments:

### `compose.yaml` - Development Environment
- **Purpose:** Local development with hot reload
- **Usage:** `docker compose up -d`
- **Features:**
  - Frontend with webpack dev server and hot reload
  - Backend with source code volume mounts for live updates
  - PostgreSQL database with persistent volume
  - Mailpit for email testing (http://localhost:8025)
  - Optional legacy node containers (can be ignored)
- **Ports:**
  - Frontend: 3000
  - Backend API: 8080
  - Database: 5432 (exposed via compose.override.yaml for host access)
  - Mailpit Web: 8025

### `compose.override.yaml` - Development Overrides
- **Purpose:** Additional development configuration
- **Usage:** Automatically merged with `compose.yaml`
- **Features:**
  - Exposes additional ports for debugging
  - Mailpit email service configuration

### `compose.prod.yaml` - Production Configuration
- **Purpose:** Production deployment (not commonly used directly)
- **Usage:** `docker compose -f compose.yaml -f compose.prod.yaml up -d`
- **Features:**
  - Optimized production builds
  - No volume mounts for source code
  - Environment-specific configurations

### `docker-compose.hostinger.yml` - Hostinger Deployment
- **Purpose:** Specific configuration for Hostinger VPS deployment
- **Usage:** `docker compose -f docker-compose.hostinger.yml up -d --build`
- **Features:**
  - Production-ready setup for Hostinger
  - See [HOSTINGER_DEPLOYMENT.md](HOSTINGER_DEPLOYMENT.md) for details

**Recommendation:** For local development, always use the default `docker compose up -d` command, which automatically uses `compose.yaml` and `compose.override.yaml`.

## 🚀 Production Deployment

### Docker Deployment to Hostinger

The application is designed for containerized deployment with separate frontend and backend containers:

```bash
# Verify deployment setup
./verify-deployment.sh

# Deploy with Docker Compose
docker compose -f docker-compose.hostinger.yml up -d --build
```

**Services:**
- `frontend` - React SPA served by Nginx (port 3000)
- `php` - Symfony backend API (PHP-FPM)
- `nginx` - Backend API gateway (port 8080)
- `database` - PostgreSQL database

For complete deployment instructions, see:
- **[📖 Hostinger Deployment Guide](HOSTINGER_DEPLOYMENT.md)**

The guide includes:
- Complete Docker setup for production
- Database configuration
- SSL/HTTPS setup
- Frontend-backend communication
- Monitoring and maintenance
- Troubleshooting

### Environment Configuration

**Frontend (.env):**
```bash
REACT_APP_API_URL=https://your-api-domain.com
```

**Backend (.env.prod):**
```bash
APP_ENV=prod
APP_SECRET=your-secret-key
DATABASE_URL=postgresql://user:pass@database:5432/dbname
SUPER_ADMIN_EMAIL=admin@example.com
SUPER_ADMIN_PASSWORD=secure-password
```

## 🔐 Security & Authentication

The application uses Symfony Security with form-based authentication and role-based access control.

### User Roles

- **ROLE_USER**: Can view and complete tasks
- **ROLE_ADMIN**: Can manage users, create tasks, and approve completed tasks

### Access Control

- `/login` - Public access
- `/task` - Requires ROLE_USER
- `/task/approve` - Requires ROLE_ADMIN
- `/user` - Requires ROLE_ADMIN

### Super Admin Setup

The application uses environment variables to configure a super admin user. This allows easy deployment and configuration across different environments.

1. Configure super admin credentials in `.env`:
```bash
SUPER_ADMIN_EMAIL=admin@familyplan.local
SUPER_ADMIN_NAME="Super Admin"
SUPER_ADMIN_PASSWORD=admin123
```

2. Create/update the super admin user:
```bash
php bin/console app:create-super-admin
```

This command will:
- Create a new admin user if one doesn't exist with the configured email
- Update the password if the user already exists
- Promote the user to admin if they aren't already

### Authentication Flow

1. User visits protected route
2. Redirected to `/login` if not authenticated
3. Form login with email/password
4. On success, redirected to home page
5. User info and logout button shown in navbar

### Security Architecture

- **UserProvider**: Custom provider that loads users from the database via UserRepository
- **Password Hashing**: Automatic hashing using Symfony's password hasher (bcrypt)
- **CSRF Protection**: Enabled on login form
- **User Entity**: Implements Symfony's `UserInterface` and `PasswordAuthenticatedUserInterface`

## 🔧 Troubleshooting

### Docker Development Issues

**Services won't start:**
```bash
# Check if ports are already in use
lsof -i :3000  # Frontend port
lsof -i :8080  # Backend port
lsof -i :5432  # Database port

# Stop conflicting services or change ports in compose.yaml
```

**Frontend shows "Cannot connect to API":**
```bash
# Check if backend is running
docker compose ps

# Check backend logs
docker compose logs nginx
docker compose logs php

# Verify backend is accessible
curl http://localhost:8080/api
```

**Database connection errors:**
```bash
# Check if database is healthy
docker compose ps database

# View database logs
docker compose logs database

# Restart database service
docker compose restart database

# If needed, recreate database
docker compose down -v
docker compose up -d
```

**Frontend changes not reflecting:**
```bash
# Restart frontend service
docker compose restart frontend

# Check if hot reload is working
docker compose logs -f frontend

# Rebuild frontend container
docker compose up -d --build frontend
```

**Backend changes not reflecting:**
```bash
# Clear Symfony cache
docker compose exec php php bin/console cache:clear

# Restart PHP service
docker compose restart php

# Check if files are mounted correctly
docker compose exec php ls -la /app/src
```

**"Permission denied" errors:**
```bash
# Fix file permissions (Linux/Mac)
sudo chown -R $USER:$USER .

# Or run Docker commands with sudo
sudo docker compose up -d
```

**Containers keep restarting:**
```bash
# Check container logs for errors
docker compose logs frontend
docker compose logs php

# Common issues:
# - Missing dependencies: docker compose exec frontend npm install
# - Syntax errors in code: check logs for details
# - Port conflicts: change ports in compose.yaml
```

**Cannot access database from host:**
```bash
# Database port is not exposed by default in development
# To access from host, ensure compose.override.yaml has:
# database:
#   ports:
#     - "5432:5432"

# Then connect with:
# Host: localhost
# Port: 5432
# User: app
# Password: !ChangeMe!
# Database: app
```

### General Issues

**"Composer dependencies not installed":**
```bash
# Docker
docker compose exec php composer install

# Standalone
composer install
```

**"NPM dependencies not installed":**
```bash
# Frontend (Docker)
docker compose exec frontend npm install

# Frontend (Standalone)
cd frontend && npm install

# Backend legacy assets (Docker)
docker compose exec node npm install

# Backend legacy assets (Standalone)
npm install
```

**Database migrations not applied:**
```bash
# Docker
docker compose exec php php bin/console doctrine:migrations:migrate

# Standalone
php bin/console doctrine:migrations:migrate
```

**Super admin user not created:**
```bash
# Docker
docker compose exec php php bin/console app:create-super-admin

# Standalone
php bin/console app:create-super-admin
```

## 🚧 Known Issues & TODO

### Future Enhancements
- [ ] Implement password reset functionality
- [ ] Add "Remember Me" functionality
- [ ] Implement user registration (optional)
- [ ] Add login/logout functionality
- [ ] Implement role-based authorization

### Command/Query Bus
- [ ] Integrate Symfony Messenger
- [ ] Configure command bus
- [ ] Configure query bus
- [ ] Set up event dispatcher

### Testing
- [ ] Add unit tests for domain layer
- [ ] Add integration tests
- [ ] Add functional tests

## 📝 License

Proprietary

## 👥 Author

jakubciszak