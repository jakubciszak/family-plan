# State Design Pattern for Task State Management

## Overview

The Task entity uses the **State Design Pattern** to manage task state transitions and enforce business rules. This pattern encapsulates state-specific behavior in separate state classes, making the code more maintainable and extensible.

## Architecture

```
Task (Context)
  └── TaskStateInterface
        ├── PendingState
        ├── CompletedState
        ├── ApprovedState
        └── RejectedState
```

## State Transition Diagram

```
PENDING ──complete()──> COMPLETED ──approve()──> APPROVED (terminal)
                            │
                            └──reject()──> REJECTED (terminal)
```

## Benefits

1. **Single Responsibility**: Each state class handles only its specific behavior
2. **Open/Closed Principle**: New states can be added without modifying existing code
3. **Clear State Transitions**: Valid transitions are explicitly defined in each state
4. **Business Rule Enforcement**: Invalid operations throw descriptive exceptions

## State Classes

### PendingState
- **Allowed**: `complete()` → transition to CompletedState
- **Forbidden**: `approve()`, `reject()`

### CompletedState  
- **Allowed**: `approve()` → transition to ApprovedState, `reject()` → transition to RejectedState
- **Forbidden**: `complete()`

### ApprovedState (Terminal)
- **Allowed**: None (terminal state)
- **Forbidden**: All operations

### RejectedState (Terminal)
- **Allowed**: None (terminal state)  
- **Forbidden**: All operations

## Usage Example

```php
$task = Task::create(...); // Creates task in PENDING state

$task->markAsCompleted($userId); // PENDING → COMPLETED
$task->approve($adminId);        // COMPLETED → APPROVED

// This will throw DomainException
$task->reject(); // Cannot reject approved task
```

## Implementation Details

### TaskStateInterface
Defines the contract for all state classes:
- `complete(Task $task, Uuid $userId): void`
- `approve(Task $task, Uuid $adminId): void`
- `reject(Task $task): void`
- `canTransitionTo(string $newState): bool`
- `getStateName(): string`

### Task Entity
The Task entity acts as the Context in the State pattern:
- Maintains a reference to the current state object
- Delegates state-dependent operations to the state object
- Provides internal methods for state objects to perform transitions

### TaskStateFactory
Creates appropriate state objects from TaskStatus enums:
```php
TaskStateFactory::createFromStatus(TaskStatus::PENDING); // Returns PendingState
```

## Domain Events

State transitions trigger appropriate domain events:
- `complete()` → `TaskCompleted` event
- `approve()` → `TaskApproved` event
- `reject()` → no event (can be added if needed)

## Testing

The state pattern makes testing easier:
1. Test each state class independently
2. Test valid transitions
3. Test that invalid transitions throw exceptions
4. Verify domain events are recorded correctly
