# 👨‍👩‍👧‍👦 Family Plan

A modern Symfony web application for organizing family house works with children, built with Hexagonal Architecture, Domain-Driven Design (DDD), and CQRS patterns.

## 🚀 Features

- **Task Management**: Create tasks with points, frequency, and descriptions
- **User Management**: Create user and admin accounts with role-based permissions
- **Security & Authentication**: Symfony Security with form login and role-based access control
- **Approval Workflow**: Admins can review and approve completed tasks
- **Points System**: Reward system with configurable points (0-1000) per task
- **Frequency-Based Tasks**: Support for Once, Daily, Weekly, and Monthly recurring tasks
- **Modern UI**: Single Page Application experience with Symfony UX components

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

- PHP 8.3+ (ready for PHP 8.4+)
- Symfony 7.1 (ready for Symfony 8 upgrade)
- Doctrine ORM 3.x
- Symfony UX (Turbo, Live Components, Stimulus)
- Webpack Encore
- SQLite Database
- Twig Templates

## 📋 Requirements

- PHP 8.3 or higher
- Composer 2.x
- Node.js 18+ & npm
- SQLite3

## 🚀 Installation

```bash
# Clone the repository
git clone https://github.com/jakubciszak/family-plan.git
cd family-plan

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install

# Build assets
npm run build

# Create database and run migrations
php bin/console doctrine:migrations:migrate

# Create super admin user from .env configuration
php bin/console app:create-super-admin

# Start development server
php -S localhost:8000 -t public
```

Visit http://localhost:8000 in your browser.

**Default Credentials:**
- Email: `admin@familyplan.local`
- Password: `admin123`

You can change these in your `.env` file:
```bash
SUPER_ADMIN_EMAIL=admin@familyplan.local
SUPER_ADMIN_NAME="Super Admin"
SUPER_ADMIN_PASSWORD=admin123
```

## 📁 Project Structure

```
src/
├── Shared/                          # Shared Kernel
│   ├── Domain/
│   │   ├── Event/                   # Domain Event interfaces
│   │   └── ValueObject/             # Shared Value Objects (Uuid)
│   └── Infrastructure/
│       ├── Bus/                     # Command/Query buses (TODO)
│       └── Persistence/
├── UserManagement/                  # User Bounded Context
│   ├── Domain/
│   │   ├── Entity/                  # User aggregate
│   │   ├── ValueObject/             # Email, Role
│   │   ├── Event/                   # Domain events
│   │   └── Repository/              # Repository interfaces
│   ├── Application/
│   │   ├── Command/                 # Commands (CreateUser)
│   │   ├── Query/                   # Queries
│   │   └── Handler/                 # Command/Query handlers
│   └── Infrastructure/
│       ├── Persistence/             # Doctrine repositories
│       └── Security/                # UserProvider for authentication
│       └── Security/                # Security adapters (TODO)
├── TaskManagement/                  # Task Bounded Context
│   ├── Domain/
│   │   ├── Entity/                  # Task aggregate
│   │   ├── ValueObject/             # TaskName, Points, Frequency, TaskStatus
│   │   ├── Event/                   # Domain events
│   │   └── Repository/              # Repository interfaces
│   ├── Application/
│   │   ├── Command/                 # Commands (CreateTask, CompleteTask, etc.)
│   │   ├── Query/                   # Queries
│   │   └── Handler/                 # Command/Query handlers
│   └── Infrastructure/
│       └── Persistence/             # Doctrine repositories
└── Presentation/
    └── Controller/                  # HTTP Controllers
```

## 🔧 Development

### Run Tests
```bash
php bin/phpunit
```

### Build Assets (Development)
```bash
npm run dev
```

### Build Assets (Production)
```bash
npm run build
```

### Watch Assets
```bash
npm run watch
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