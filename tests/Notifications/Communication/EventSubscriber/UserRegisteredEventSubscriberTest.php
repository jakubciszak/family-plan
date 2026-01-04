<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Communication\EventSubscriber;

use App\Notifications\Communication\EventSubscriber\UserRegisteredEventSubscriber;
use App\Notifications\Infrastructure\Adapter\InMemoryNotificationAdapter;
use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Event\UserRegistered;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Test for UserRegisteredEventSubscriber with mocked email sending
 */
class UserRegisteredEventSubscriberTest extends TestCase
{
    private InMemoryNotificationAdapter $adapter;
    private NotificationFacade $facade;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryNotificationAdapter();
        $this->facade = new NotificationFacade([$this->adapter]);
    }

    public function testSubscriberListensToUserRegisteredEvent(): void
    {
        // Given
        $subscribedEvents = UserRegisteredEventSubscriber::getSubscribedEvents();

        // Then
        $this->assertArrayHasKey(UserRegistered::class, $subscribedEvents);
        $this->assertEquals('onUserRegistered', $subscribedEvents[UserRegistered::class]);
    }

    public function testOnUserRegisteredSendsActivationEmail(): void
    {
        // Given
        $userRepo = new InMemoryUserRepository();
        $settingsRepo = $this->createStub(\App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface::class);
        $orchestrator = new NotificationOrchestrator($this->facade, $userRepo, $settingsRepo);
        
        $event = new UserRegistered(
            Uuid::generate(),
            Email::fromString('test@example.com'),
            'Test User',
            'test-activation-token-123',
            new \DateTimeImmutable()
        );

        $subscriber = new UserRegisteredEventSubscriber($orchestrator);

        // When
        $subscriber->onUserRegistered($event);

        // Then
        $notifications = $this->adapter->getSentNotifications();
        $this->assertCount(1, $notifications);
        $this->assertEquals('test@example.com', $notifications[0]['recipient']);
        $this->assertStringContainsString('Witaj w Family Plan', $notifications[0]['message']);
        $this->assertEquals('Aktywacja konta Family Plan', $notifications[0]['subject']);
    }

    public function testActivationEmailContainsActivationLink(): void
    {
        // Given
        $_ENV['APP_URL'] = 'http://localhost:8080';
        
        $userRepo = new InMemoryUserRepository();
        $settingsRepo = $this->createStub(\App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface::class);
        $orchestrator = new NotificationOrchestrator($this->facade, $userRepo, $settingsRepo);
        
        $activationToken = 'my-unique-token-456';
        $event = new UserRegistered(
            Uuid::generate(),
            Email::fromString('user@example.com'),
            'User Name',
            $activationToken,
            new \DateTimeImmutable()
        );

        $subscriber = new UserRegisteredEventSubscriber($orchestrator);

        // When
        $subscriber->onUserRegistered($event);

        // Then
        $notifications = $this->adapter->getSentNotifications();
        $this->assertCount(1, $notifications);
        $message = $notifications[0]['message'];
        $this->assertStringContainsString('Witaj w Family Plan', $message);
        $this->assertStringContainsString('Aby aktywować swoje konto kliknij w link poniżej:', $message);
        $this->assertStringContainsString($activationToken, $message);
        $this->assertStringContainsString('/api/auth/activate/', $message);
    }

    public function testActivationEmailUsesCorrectRecipient(): void
    {
        // Given
        $userRepo = new InMemoryUserRepository();
        $settingsRepo = $this->createStub(\App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface::class);
        $orchestrator = new NotificationOrchestrator($this->facade, $userRepo, $settingsRepo);
        
        $recipientEmail = 'recipient@example.com';
        $event = new UserRegistered(
            Uuid::generate(),
            Email::fromString($recipientEmail),
            'Recipient Name',
            'token-123',
            new \DateTimeImmutable()
        );

        $subscriber = new UserRegisteredEventSubscriber($orchestrator);

        // When
        $subscriber->onUserRegistered($event);

        // Then
        $notifications = $this->adapter->getSentNotifications();
        $this->assertCount(1, $notifications);
        $this->assertEquals($recipientEmail, $notifications[0]['recipient']);
    }
}
