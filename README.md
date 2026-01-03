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

## 🚀 Quick Start with Docker

The easiest way to run the complete application:

```bash
# Clone the repository
git clone https://github.com/jakubciszak/family-plan.git
cd family-plan

# Start all services (backend + frontend)
docker compose up -d

# Initialize the database (first time only)
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:create-super-admin
```

**Access the application:**
- Frontend (React SPA): http://localhost:3000
- Backend API: http://localhost:8080/api
- API Documentation: http://localhost:8080/api-docs.html

**Default Credentials:**
- Email: `admin@familyplan.local`
- Password: `admin123`

## 🚀 Installation

### Frontend Development

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

Frontend will be available at http://localhost:3000

### Backend Development

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

### Building Frontend Assets (Legacy)

The main repository still contains Webpack Encore for legacy Symfony frontend support:

```bash
npm install
npm run build
```

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
│   ├── php/                         # PHP-FPM container
│   ├── nginx/                       # Nginx container
│   └── react/                       # Legacy React container (deprecated)
│
├── compose.yaml                     # Development compose file
├── compose.prod.yaml                # Production compose file
├── docker-compose.hostinger.yml     # Hostinger deployment compose
└── README.md                        # This file
```

## 🔧 Development

### Frontend Development
```bash
cd frontend
npm start                    # Start dev server with hot reload
npm run build               # Production build
npm run watch               # Watch mode
```

### Backend Development
```bash
# Run tests
php bin/phpunit

# Database migrations
php bin/console doctrine:migrations:migrate

# Clear cache
php bin/console cache:clear
```

### Docker Development
```bash
# Start all services
docker compose up -d

# View logs
docker compose logs -f frontend
docker compose logs -f php

# Rebuild containers
docker compose up -d --build

# Stop all services
docker compose down
```

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