<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Domain\Repository\PushSubscriptionRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class PushAnnouncements
{
    private const DEFAULT_TITLE = 'Family Plan';

    public function __construct(
        private PushSubscriptionRepositoryInterface $subscriptions,
        private NotificationFacade $notifications
    ) {
    }

    public function toEveryone(string $message, ?string $title = null): int
    {
        $reached = 0;

        foreach ($this->subscriptions->usersReachableByPush() as $userId) {
            $this->push($userId, $message, $title);
            ++$reached;
        }

        return $reached;
    }

    public function toOne(Uuid $userId, string $message, ?string $title = null): bool
    {
        if ($this->subscriptions->countForUser($userId) === 0) {
            return false;
        }

        $this->push($userId, $message, $title);

        return true;
    }

    private function push(Uuid $userId, string $message, ?string $title): void
    {
        $this->notifications->sendPush(
            $userId->value(),
            $message,
            $this->titleOrDefault($title),
            ['tag' => 'announcement']
        );
    }

    private function titleOrDefault(?string $title): string
    {
        $title = trim((string) $title);

        return $title === '' ? self::DEFAULT_TITLE : $title;
    }
}
