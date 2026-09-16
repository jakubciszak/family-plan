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

    /**
     * @param iterable<Uuid> $userIds
     */
    public function to(iterable $userIds, string $message, ?string $title = null): int
    {
        $reached = 0;

        foreach ($userIds as $userId) {
            if (!$this->reachable($userId)) {
                continue;
            }

            $this->notifications->sendPush(
                $userId->value(),
                $message,
                $this->titleOrDefault($title),
                ['tag' => 'announcement']
            );

            ++$reached;
        }

        return $reached;
    }

    public function reachable(Uuid $userId): bool
    {
        return $this->subscriptions->countForUser($userId) > 0;
    }

    private function titleOrDefault(?string $title): string
    {
        $title = trim((string) $title);

        return $title === '' ? self::DEFAULT_TITLE : $title;
    }
}
