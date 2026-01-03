# Notifications Bounded Context

A generic, open-host notification service that enables other bounded contexts to send notifications through various communication channels.

## Overview

The Notifications context provides a flexible, port-and-adapter based architecture for sending notifications via different channels (Email, SMS, etc.). It's designed to be consumed by other bounded contexts (TaskManagement, UserManagement, PointsManagement) following Domain-Driven Design principles.

## Architecture

This context follows Hexagonal Architecture (Ports and Adapters pattern):

### Domain Layer
- **Value Objects:**
  - `NotificationChannel` - Represents communication channels (email, sms)
  - `Recipient` - Represents notification recipient (email address or phone number)
  - `NotificationMessage` - Contains message content, optional subject, and additional parameters

- **Ports:**
  - `NotificationPortInterface` - Interface that all adapters must implement

### Application Layer
- **Commands:**
  - `SendNotificationCommand` - Command for sending notifications

- **Handlers:**
  - `SendNotificationHandler` - Handles notification sending by delegating to appropriate adapter

- **Services:**
  - `NotificationFacade` - Facade service providing simple methods for sending notifications

### Infrastructure Layer
- **Adapters:**
  - `EmailNotificationAdapter` - Email notification implementation (logs for now, ready for Symfony Mailer)
  - `SmsNotificationAdapter` - SMS notification implementation (logs for now, ready for Twilio/Vonage)
  - `InMemoryNotificationAdapter` - In-memory adapter for testing

## Usage

### Using the Facade (Recommended)

The `NotificationFacade` provides the simplest way to send notifications:

```php
use App\Notifications\Application\Service\NotificationFacade;

class TaskApprovalService
{
    public function __construct(
        private NotificationFacade $notificationService
    ) {}
    
    public function notifyTaskApproved(string $userEmail, string $taskName, int $points): void
    {
        $this->notificationService->sendEmail(
            recipient: $userEmail,
            message: "Your task '{$taskName}' has been approved! You earned {$points} points.",
            subject: 'Task Approved',
            additionalParameters: ['taskName' => $taskName, 'points' => $points]
        );
    }
}
```

### Sending SMS

```php
$this->notificationService->sendSms(
    recipient: '+48123456789',
    message: 'New task assigned: Clean kitchen',
    additionalParameters: ['taskId' => '123']
);
```

### Using Commands (Alternative)

For CQRS-based approach using the command bus:

```php
use App\Notifications\Application\Command\SendNotificationCommand;

$command = new SendNotificationCommand(
    channel: 'email',
    recipient: 'user@example.com',
    message: 'Your message content',
    subject: 'Message subject',
    additionalParameters: ['key' => 'value']
);

$commandBus->dispatch($command);
```

## Adding New Communication Channels

To add a new communication channel (e.g., Push Notifications, Slack):

1. **Add channel to NotificationChannel value object:**
```php
// src/Notifications/Domain/ValueObject/NotificationChannel.php
private const PUSH = 'push';

public static function push(): self
{
    return new self(self::PUSH);
}

public function isPush(): bool
{
    return $this->value === self::PUSH;
}
```

2. **Create a new adapter:**
```php
// src/Notifications/Infrastructure/Adapter/PushNotificationAdapter.php
class PushNotificationAdapter implements NotificationPortInterface
{
    public function send(
        Recipient $recipient,
        NotificationMessage $message,
        NotificationChannel $channel
    ): void {
        // Implementation
    }
    
    public function supports(NotificationChannel $channel): bool
    {
        return $channel->isPush();
    }
}
```

3. **Register the adapter in services.yaml:**
```yaml
App\Notifications\Infrastructure\Adapter\PushNotificationAdapter:
    tags: ['notification.adapter']
```

4. **Add convenience method to NotificationFacade:**
```php
public function sendPush(
    string $recipient,
    string $message,
    array $additionalParameters = []
): void {
    $this->send('push', $recipient, $message, null, $additionalParameters);
}
```

## Testing

### Running Tests

```bash
docker compose run --rm php vendor/bin/phpunit tests/Notifications/
```

### Using Mother Classes

Mother classes help create test data easily:

```php
use App\Tests\Notifications\Mother\NotificationChannelMother;
use App\Tests\Notifications\Mother\RecipientMother;
use App\Tests\Notifications\Mother\NotificationMessageMother;

$channel = NotificationChannelMother::email();
$recipient = RecipientMother::email('test@example.com');
$message = NotificationMessageMother::email('Test Subject');
```

### Using InMemoryAdapter for Testing

```php
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;

$adapter = new InMemoryNotificationAdapter();
$facade = new NotificationFacade([$adapter]);

// Send notifications
$facade->sendEmail('test@example.com', 'Test message', 'Subject');

// Verify in tests
$sent = $adapter->getSentNotifications();
$this->assertCount(1, $sent);
$this->assertEquals('test@example.com', $sent[0]['recipient']);

// Clear for next test
$adapter->clear();
```

## Integration Examples

### TaskManagement Context

```php
// When a task is approved
$this->notificationService->sendEmail(
    $user->email(),
    "Your task '{$task->name()}' has been approved!",
    'Task Approved',
    ['taskId' => $task->id(), 'points' => $task->points()]
);
```

### PointsManagement Context

```php
// When bonus points are awarded
$this->notificationService->sendEmail(
    $user->email(),
    "Congratulations! You earned {$bonusPoints} bonus points!",
    'Bonus Points Earned',
    ['points' => $bonusPoints, 'reason' => 'Weekly streak']
);
```

### UserManagement Context

```php
// Welcome new user
$this->notificationService->sendEmail(
    $user->email(),
    'Welcome to Family Plan! Start organizing your family tasks today.',
    'Welcome to Family Plan'
);
```

## Future Enhancements

- [ ] Implement actual email sending using Symfony Mailer
- [ ] Implement actual SMS sending using Twilio/Vonage
- [ ] Add Push Notification support
- [ ] Add Slack/Discord webhook support
- [ ] Add notification templates system
- [ ] Add notification preferences per user
- [ ] Add notification queue/scheduling
- [ ] Add retry mechanism for failed notifications
- [ ] Add notification history/audit log

## Configuration

The notification adapters are configured in `config/services.yaml`:

```yaml
# Notification adapters
App\Notifications\Infrastructure\Adapter\EmailNotificationAdapter:
    tags: ['notification.adapter']

App\Notifications\Infrastructure\Adapter\SmsNotificationAdapter:
    tags: ['notification.adapter']

App\Notifications\Application\Handler\SendNotificationHandler:
    arguments:
        $adapters: !tagged_iterator notification.adapter

App\Notifications\Application\Service\NotificationFacade:
    arguments:
        $adapters: !tagged_iterator notification.adapter
```
