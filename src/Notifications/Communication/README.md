# Communication Sub-Context & User Preferences Implementation

## Overview

This implementation adds event-driven notifications with user preference management to the Family Plan application.

## What Was Implemented

### 1. UserSettings Bounded Context (Generic Preferences Model)

A completely generic preference system based on the **Preferences archetype pattern**:

#### Domain Model

**PreferenceType** - Categorizes preferences
```php
PreferenceType::notifications() // Currently supported
PreferenceType::fromString('notifications')
```

**PreferenceOption** - Individual option within a preference type
```php
PreferenceOption::create('email', true)  // name, enabled
PreferenceOption::create('sms', false)
```

**UserPreference** - A preference of a specific type with multiple options
```php
UserPreference::create(
    PreferenceType::notifications(),
    [
        PreferenceOption::create('email', true),
        PreferenceOption::create('sms', false),
    ]
)
```

**UserPreferences** - Collection of all user preferences
```php
UserPreferences::defaultNotificationPreferences()
$preferences->getByType(PreferenceType::notifications())
$preferences->update($newPreference)
```

**UserSettings** - Aggregate root linking user to their preferences
```php
UserSettings::create($userId, $preferences)
$settings->updatePreference($preference)
$settings->getPreferenceByType(PreferenceType::notifications())
```

#### Infrastructure

- `UserSettingsRepository` - Doctrine ORM repository
- `UserPreferencesType` - Custom Doctrine type for JSON storage
- Database migration `Version20260103220000.php`

#### Application Layer

- `UpdateUserSettingsCommand` - Command to update user preferences
- `UpdateUserSettingsHandler` - Handler for preference updates

### 2. Communication Sub-Context (Event-Driven Notifications)

Located in `src/Notifications/Communication/`

#### Services

**NotificationOrchestrator** - Orchestrates notification sending based on user preferences
```php
$orchestrator->notifyUser(
    $userId,
    'Your task has been approved!',
    'Task Approved',
    ['task_id' => '123', 'points' => 50]
);
```

#### Event Subscribers

**TaskApprovedEventSubscriber**
- Listens to: `TaskApproved` events
- Action: Sends notification to the user who completed the task
- Message: "Your task '{name}' has been approved! You earned {points} points."

**TaskCompletedEventSubscriber**
- Listens to: `TaskCompleted` events
- Action: Sends notification to all admin users
- Message: "User {name} has completed task '{task_name}'."

**UserCreatedEventSubscriber**
- Listens to: `UserCreated` events
- Action: Sends welcome notification to new user
- Message: "Welcome to Family Plan! Start organizing your family tasks today."

### 3. API Endpoints

**GET /api/user-settings/{userId}**
- Returns user's preferences

**PUT /api/user-settings/{userId}**
- Updates user preferences

Request body:
```json
{
  "preference_type": "notifications",
  "options": [
    {"name": "email", "enabled": true},
    {"name": "sms", "enabled": false}
  ]
}
```

### 4. Database Schema

Table: `user_settings`
```sql
- id (SERIAL PRIMARY KEY)
- user_id (UUID UNIQUE)
- preferences (JSON)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

The JSON preferences structure:
```json
[
  {
    "type": "notifications",
    "options": [
      {"name": "email", "enabled": true},
      {"name": "sms", "enabled": false}
    ]
  }
]
```

## Architecture Highlights

### Generic Preferences Model

The preferences system is designed to be extensible:

1. **PreferenceType** - Can easily add new types (theme, language, privacy, etc.)
2. **PreferenceOption** - Options are dynamic, not hardcoded
3. **UserPreference** - Each preference type can have any number of options
4. **UserPreferences** - Collection can hold multiple preference types

Example of adding a new preference type:
```php
// 1. Add to PreferenceType
private const THEME = 'theme';

public static function theme(): self
{
    return new self(self::THEME);
}

// 2. Use it
$themePreference = UserPreference::create(
    PreferenceType::theme(),
    [
        PreferenceOption::create('dark_mode', true),
        PreferenceOption::create('compact_view', false),
    ]
);
```

### Event-Driven Architecture

The system uses Symfony's EventDispatcher:

1. Domain events are raised (TaskApproved, TaskCompleted, UserCreated)
2. Event subscribers listen and react
3. NotificationOrchestrator checks user preferences
4. Notifications sent via appropriate channels

### Channel Selection Flow

```
1. Event occurs (e.g., TaskApproved)
2. EventSubscriber catches event
3. Calls NotificationOrchestrator.notifyUser()
4. Orchestrator loads UserSettings for the user
5. Gets enabled notification channels from preferences
6. Sends notification via each enabled channel
7. Falls back to email if no settings found
```

## Usage Examples

### Backend - Update User Preferences

```php
$command = new UpdateUserSettingsCommand(
    userId: '123e4567-e89b-12d3-a456-426614174000',
    preferenceType: 'notifications',
    options: [
        ['name' => 'email', 'enabled' => true],
        ['name' => 'sms', 'enabled' => false],
    ]
);

$commandBus->dispatch($command);
```

### Frontend - API Integration

```typescript
// Get user settings
const response = await fetch(`/api/user-settings/${userId}`);
const { preferences } = await response.json();

// Update settings
await fetch(`/api/user-settings/${userId}`, {
  method: 'PUT',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    preference_type: 'notifications',
    options: [
      { name: 'email', enabled: true },
      { name: 'sms', enabled: false }
    ]
  })
});
```

## Testing

Comprehensive tests included:

### Domain Tests
- `PreferenceTypeTest` - Type validation and creation
- `PreferenceOptionTest` - Option creation and state management
- `UserPreferenceTest` - Preference with options
- `UserPreferencesTest` - Collection operations
- `UserSettingsTest` - Aggregate functionality

### Integration Tests
- Event subscriber tests (to be completed)
- Full notification flow tests (to be completed)

## Future Enhancements

1. **Additional Preference Types**
   - Theme preferences (dark mode, colors)
   - Language preferences
   - Privacy settings
   - Display preferences

2. **Enhanced Notification Options**
   - Push notifications (web and mobile)
   - Slack/Discord webhooks
   - In-app notifications

3. **Advanced Features**
   - Notification schedules (quiet hours)
   - Priority levels per channel
   - Notification templates
   - User notification history

4. **Frontend Components**
   - Settings page with preference management
   - Real-time notification previews
   - Channel-specific configuration

## Configuration

Services are auto-configured via Symfony's autoconfiguration.

Key services:
- `UserSettingsRepositoryInterface` → `DoctrineUserSettingsRepository`
- Event subscribers auto-registered via `EventSubscriberInterface`
- Custom Doctrine type registered in `doctrine.yaml`

## Migration

Run migration to create user_settings table:
```bash
php bin/console doctrine:migrations:migrate
```

## Files Created

- **Domain**: 8 files (PreferenceType, PreferenceOption, UserPreference, UserPreferences, UserSettings, Repository)
- **Application**: 2 files (Command, Handler)
- **Infrastructure**: 2 files (Repository, Doctrine Type)
- **Communication**: 4 files (Orchestrator, 3 Event Subscribers)
- **Presentation**: 1 file (API Controller)
- **Tests**: 5 files (Domain tests)
- **Database**: 1 migration file
- **Total**: 23 new files

## API Documentation

### Get User Settings

```http
GET /api/user-settings/{userId}
```

Response:
```json
{
  "preferences": [
    {
      "type": "notifications",
      "options": [
        {"name": "email", "enabled": true},
        {"name": "sms", "enabled": false}
      ]
    }
  ]
}
```

### Update User Settings

```http
PUT /api/user-settings/{userId}
Content-Type: application/json

{
  "preference_type": "notifications",
  "options": [
    {"name": "email", "enabled": true},
    {"name": "sms", "enabled": true}
  ]
}
```

Response:
```json
{
  "status": "success"
}
```

## Notes

- SMS notifications are logged but not fully implemented (requires phone number on User entity)
- Event subscribers are auto-registered by Symfony
- Default preferences: email enabled, SMS disabled
- Preferences are stored as JSON in database for flexibility
