<?php

declare(strict_types=1);

namespace App\Party\Communication\EventSubscriber;

use App\Party\Application\Service\PartyAdapter;
use App\UserManagement\Domain\Event\UserCreated;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * User Created Event Subscriber
 *
 * Automatically creates a Person entity when a User is created.
 * This keeps the Party model synchronized with the legacy User model.
 */
class UserCreatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PartyAdapter $partyAdapter,
        private UserRepositoryInterface $userRepository
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
        // Find the user
        $user = $this->userRepository->findById($event->userId());

        if ($user === null) {
            return;
        }

        // Create corresponding Person in Party model
        $this->partyAdapter->getOrCreatePerson($user);
    }
}
