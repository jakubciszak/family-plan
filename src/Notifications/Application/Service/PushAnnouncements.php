<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Domain\Repository\PushSubscriptionRepositoryInterface;
use App\Notifications\Domain\ValueObject\DeliveryParameters;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class PushAnnouncements
{
    private const DEFAULT_TITLE = 'Family Plan';
    private const TTL = 86400;

    public function __construct(
        private PushSubscriptionRepositoryInterface $subscriptions,
        private NotificationFacade $notifications,
        private ?PushReach $reach = null
    ) {
    }

    /**
     * @param iterable<Uuid> $userIds
     */
    public function to(iterable $userIds, string $message, ?string $title = null): int
    {
        $reached = 0;
        // Its own tag: a second message must not silently replace the first one in the tray.
        $tag = 'announcement-' . Uuid::generate()->value();

        foreach ($userIds as $userId) {
            if (!$this->reachable($userId)) {
                continue;
            }

            $this->notifications->sendPush(
                $userId->value(),
                $message,
                $this->titleOrDefault($title),
                ['tag' => $tag, DeliveryParameters::TTL => self::TTL, DeliveryParameters::URGENCY => 'high']
            );

            ++$reached;
        }

        return $reached;
    }

    public function reachable(Uuid $userId): bool
    {
        return ($this->reach?->devicesOf($userId) ?? $this->subscriptions->countForUser($userId)) > 0;
    }

    private function titleOrDefault(?string $title): string
    {
        $title = trim((string) $title);

        return $title === '' ? self::DEFAULT_TITLE : $title;
    }
}
