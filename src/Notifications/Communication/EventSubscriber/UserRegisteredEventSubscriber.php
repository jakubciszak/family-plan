<?php

declare(strict_types=1);

namespace App\Notifications\Communication\EventSubscriber;

use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\UserManagement\Domain\Event\UserRegistered;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to UserRegistered events and sends activation email
 */
final readonly class UserRegisteredEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationOrchestrator $notificationOrchestrator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserRegistered::class => 'onUserRegistered',
        ];
    }

    public function onUserRegistered(UserRegistered $event): void
    {
        $activationUrl = sprintf(
            '%s/api/auth/activate/%s',
            $_ENV['APP_URL'] ?? 'http://localhost:8080',
            $event->activationToken()
        );

        $message = sprintf(
            "Witaj w Family Plan<br><br>Aby aktywować swoje konto kliknij w link poniżej:<br><br><a href=\"%s\">%s</a>",
            $activationUrl,
            $activationUrl
        );

        $this->notificationOrchestrator->notifyEmail(
            $event->email()->value(),
            $message,
            'Aktywacja konta Family Plan'
        );
    }
}
