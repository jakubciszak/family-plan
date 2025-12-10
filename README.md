# 👨‍👩‍👧‍👦 Family Plan

A modern Symfony web application for organizing family house works with children, built with Hexagonal Architecture, Domain-Driven Design (DDD), and CQRS patterns.

## 🚀 Features

- **Task Management**: Create tasks with points, frequency, and descriptions
- **User Management**: Create user and admin accounts with role-based permissions
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

# Start development server
php -S localhost:8000 -t public
```

Visit http://localhost:8000 in your browser.

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

## 🚧 Known Issues & TODO

### ORM Mapping for Value Objects
The application currently has an issue with Doctrine ORM persistence of Value Objects and Enums. This needs to be resolved by:
1. Creating custom Doctrine types for value objects
2. Or using Doctrine lifecycle callbacks
3. Or adding property accessors with proper annotations

### Security Implementation
- [ ] Configure Symfony Security
- [ ] Implement user authentication
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