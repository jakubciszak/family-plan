<?php

declare(strict_types=1);

namespace App\Notifications\Communication\EventSubscriber;

use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\UserManagement\Domain\Event\UserCreated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to UserCreated events and sends welcome notifications
 */
final readonly class UserCreatedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationOrchestrator $notificationOrchestrator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserCreated::class => 'onUserCreated',
        ];
    }

    public function onUserCreated(UserCreated $event): void
    {
        $message = 'Welcome to Family Plan! Start organizing your family tasks today.';

        $this->notificationOrchestrator->notifyUser(
            $event->userId(),
            $message,
            'Welcome to Family Plan',
            [
                'email' => $event->email()->value(),
                'role' => $event->role()->value,
            ]
        );
    }
}
