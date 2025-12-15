# Routine Task Management - Implementation Summary

## Overview

This implementation adds comprehensive routine task management functionality to the Family Plan application, following the project's hexagonal architecture, DDD patterns, and CQRS approach.

## Architecture

### Domain Layer

#### Entities
1. **RoutineTask** (`src/TaskManagement/Domain/Entity/RoutineTask.php`)
   - Represents a recurring task template
   - Properties: id, name, description, points, frequency, scheduleConfig, isActive, assignedUserId
   - Methods: create(), activate(), deactivate(), update(), assignTo()
   - Records RoutineTaskCreated domain event

2. **TaskExecution** (`src/TaskManagement/Domain/Entity/TaskExecution.php`)
   - Represents a specific task occurrence (instance)
   - Can be linked to a RoutineTask (recurring) or standalone (one-time)
   - Properties: id, routineTaskId, name, description, points, scheduledFor, status, etc.
   - Uses State Pattern for status transitions
   - Records TaskExecutionCreated, TaskExecutionCompleted, TaskExecutionApproved events

#### Value Objects
1. **ScheduleConfig** (`src/TaskManagement/Domain/ValueObject/ScheduleConfig.php`)
   - Supports multiple schedule types:
     - `daily`: Every day
     - `weekly`: Specific day of week (1-7, Monday-Sunday)
     - `monthly`: Specific day of month (1-31)
     - `times_per_week`: X times per week (1-7)
     - `once`: One-time only
   - Immutable with validation
   - Serialized as JSON in database

2. **ExecutionStatus** (`src/TaskManagement/Domain/ValueObject/ExecutionStatus.php`)
   - Enum with: PENDING, COMPLETED, APPROVED, REJECTED
   - Similar to TaskStatus but for executions

#### State Pattern
- **ExecutionStateInterface** - Contract for execution states
- **PendingExecutionState** - Can transition to completed
- **CompletedExecutionState** - Can transition to approved or rejected
- **ApprovedExecutionState** - Terminal state
- **RejectedExecutionState** - Terminal state
- **ExecutionStateFactory** - Creates appropriate state from status

#### Domain Events
1. **RoutineTaskCreated** - When routine task is created
2. **TaskExecutionCreated** - When execution is created
3. **TaskExecutionCompleted** - When execution is completed by user
4. **TaskExecutionApproved** - When execution is approved by admin

#### Repository Interfaces
1. **RoutineTaskRepositoryInterface** - Methods: save, findById, findAll, findActive, findByAssignedUser, delete
2. **TaskExecutionRepositoryInterface** - Methods: save, findById, findAll, findByRoutineTask, findByAssignedUser, findPending, findCompleted, findScheduledForDate, delete

### Application Layer

#### Commands
1. **CreateRoutineTaskCommand** - Create new routine task
2. **CreateTaskExecutionCommand** - Create task execution (from routine or one-time)
3. **CompleteTaskExecutionCommand** - Mark execution as completed
4. **ApproveTaskExecutionCommand** - Approve completed execution

#### Handlers
1. **CreateRoutineTaskHandler** - Handles routine task creation
2. **CreateTaskExecutionHandler** - Handles execution creation (supports both types)
3. **CompleteTaskExecutionHandler** - Handles execution completion
4. **ApproveTaskExecutionHandler** - Handles execution approval

### Infrastructure Layer

#### Doctrine Repositories
1. **DoctrineRoutineTaskRepository** - PostgreSQL implementation with QueryBuilder for filtering
2. **DoctrineTaskExecutionRepository** - PostgreSQL implementation with date range queries

#### Custom Doctrine Types
1. **ScheduleConfigType** - Converts ScheduleConfig to/from JSON
2. **ExecutionStatusType** - Converts ExecutionStatus enum to/from string

### Presentation Layer

#### Controllers

**RoutineTaskController** (`/routine-tasks`)
- `list()` - GET `/` - List all routine tasks
- `active()` - GET `/active` - List active routine tasks only
- `create()` - GET|POST `/create` - Create new routine task
- `view()` - GET `/{id}` - View routine task details
- `activate()` - POST `/{id}/activate` - Activate routine task
- `deactivate()` - POST `/{id}/deactivate` - Deactivate routine task

**TaskExecutionController** (`/task-executions`)
- `list()` - GET `/` - List all task executions
- `pending()` - GET `/pending` - List pending executions
- `completed()` - GET `/completed` - List completed executions
- `today()` - GET `/today` - List today's executions
- `create()` - GET|POST `/create` - Create task execution (one-time or from routine)
- `createFromRoutine()` - GET|POST `/create-from-routine/{routineTaskId}` - Quick create from routine
- `view()` - GET `/{id}` - View execution details
- `complete()` - POST `/{id}/complete` - Complete execution
- `approve()` - POST `/{id}/approve` - Approve execution
- `reject()` - POST `/{id}/reject` - Reject execution

#### Templates

**Routine Task Templates** (`templates/routine_task/`)
- `list.html.twig` - Grid view with status badges, schedule display
- `create.html.twig` - Form with dynamic schedule configuration fields
- `view.html.twig` - Detailed view with actions

**Task Execution Templates** (`templates/task_execution/`)
- `list.html.twig` - Grid view with filtering, status badges
- `create.html.twig` - Form supporting both one-time and routine-based tasks
- `create_from_routine.html.twig` - Simplified form for routine-based execution
- `view.html.twig` - Detailed view with linked routine task

## Database Schema

### routine_tasks table
```sql
- id (UUID, PK)
- name (VARCHAR(255))
- description (TEXT)
- points (INT)
- frequency (VARCHAR(50))
- schedule_config (VARCHAR(500)) -- JSON
- is_active (BOOLEAN)
- assigned_user_id (UUID, nullable)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP, nullable)

Indexes:
- is_active
- assigned_user_id
```

### task_executions table
```sql
- id (UUID, PK)
- routine_task_id (UUID, nullable) -- NULL for one-time tasks
- name (VARCHAR(255), nullable) -- Used for one-time tasks
- description (TEXT, nullable) -- Used for one-time tasks
- points (INT, nullable) -- Used for one-time tasks
- scheduled_for (TIMESTAMP)
- status (VARCHAR(50))
- assigned_user_id (UUID, nullable)
- completed_by_user_id (UUID, nullable)
- completed_at (TIMESTAMP, nullable)
- approved_by_admin_id (UUID, nullable)
- approved_at (TIMESTAMP, nullable)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP, nullable)

Indexes:
- status
- routine_task_id
- assigned_user_id
- scheduled_for
```

## Testing

**Unit Tests**: 15 tests, 51 assertions, all passing
- Test location: `tests/TaskManagement/Domain/RoutineTaskManagementTest.php`
- Uses Mother pattern for test data builders
- Custom assertions for domain-specific validations
- Follows Detroit school of TDD

**Test Coverage**:
- RoutineTask creation, activation/deactivation
- TaskExecution creation (both types)
- State transitions (pending → completed → approved/rejected)
- Domain event recording
- Schedule configuration variations

## Key Features

1. **Dual Task Types**:
   - Recurring tasks (linked to RoutineTask)
   - One-time tasks (standalone TaskExecution)

2. **Flexible Scheduling**:
   - Daily, weekly, monthly, X times per week, one-time
   - Specific day selection for weekly/monthly

3. **State Management**:
   - Clear state transitions with validation
   - Prevents invalid state changes

4. **Admin Approval Workflow**:
   - Users complete tasks
   - Admins approve or reject
   - Points awarded on approval

5. **Frontend Integration**:
   - Full CRUD operations via web interface
   - Responsive design
   - Dynamic forms with JavaScript
   - Navigation integration

## Usage Examples

### Create a Weekly Routine Task
```php
$command = new CreateRoutineTaskCommand(
    id: Uuid::generate()->value(),
    name: 'Clean kitchen',
    description: 'Wash dishes and wipe counters',
    points: 10,
    frequency: 'weekly',
    scheduleConfig: [
        'type' => 'weekly',
        'day_of_week' => 1 // Monday
    ]
);
```

### Create Task Execution from Routine
```php
$command = new CreateTaskExecutionCommand(
    id: Uuid::generate()->value(),
    routineTaskId: $routineTaskId,
    name: null, // Inherited from RoutineTask
    description: null,
    points: null,
    scheduledFor: '2025-01-15 10:00:00'
);
```

### Create One-Time Task
```php
$command = new CreateTaskExecutionCommand(
    id: Uuid::generate()->value(),
    routineTaskId: null, // No routine task
    name: 'Fix broken window',
    description: 'Replace glass in kitchen window',
    points: 50,
    scheduledFor: '2025-01-20 14:00:00'
);
```

## Configuration

**Service Registration** (`config/services.yaml`):
```yaml
App\TaskManagement\Domain\Repository\RoutineTaskRepositoryInterface:
    class: App\TaskManagement\Infrastructure\Persistence\DoctrineRoutineTaskRepository

App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface:
    class: App\TaskManagement\Infrastructure\Persistence\DoctrineTaskExecutionRepository
```

**Doctrine Types** (`config/packages/doctrine.yaml`):
```yaml
types:
    schedule_config: App\TaskManagement\Infrastructure\Persistence\Doctrine\Type\ScheduleConfigType
    execution_status: App\TaskManagement\Infrastructure\Persistence\Doctrine\Type\ExecutionStatusType
```

## Migration

Run migration to create tables:
```bash
php bin/console doctrine:migrations:migrate
```

Migration file: `migrations/Version20251215144200.php`

## Next Steps (Optional Enhancements)

1. **Auto-generation of Executions**: Background service to automatically create TaskExecutions based on RoutineTask schedules
2. **Calendar View**: Display scheduled executions in calendar format
3. **Notifications**: Alert users about upcoming/overdue tasks
4. **Statistics**: Dashboard showing completion rates, earned points
5. **Task Assignment**: Enhanced user assignment with notifications
6. **Recurring Execution History**: View all past executions of a routine task
7. **Schedule Conflict Detection**: Warn if too many tasks scheduled for same time
8. **Task Dependencies**: Chain tasks that must be completed in order

## Technical Notes

- **PHP Version**: Requires PHP 8.4+ (tested with 8.5)
- **Symfony Version**: Symfony 8
- **Database**: PostgreSQL
- **Testing**: Use Docker for PHP 8.5 CLI environment
- **Architecture**: Follows hexagonal architecture with strict layer separation
- **Design Patterns**: DDD, CQRS, State Pattern, Repository Pattern, Mother Pattern (testing)
