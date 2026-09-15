<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Adapter;

use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\Port\NotificationPortInterface;
use App\Notifications\Domain\Port\PushDelivery;
use App\Notifications\Domain\Port\PushSenderInterface;
use App\Notifications\Domain\Repository\PushSubscriptionRepositoryInterface;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class PushNotificationAdapter implements NotificationPortInterface
{
    public function __construct(
        private PushSubscriptionRepositoryInterface $subscriptions,
        private PushSenderInterface $sender,
        private ClockInterface $clock
    ) {
    }

    public function send(
        Recipient $recipient,
        NotificationMessage $message,
        NotificationChannel $channel
    ): void {
        if (!$channel->isPush()) {
            throw new \InvalidArgumentException('PushNotificationAdapter only supports the push channel');
        }

        if (!$recipient->isUserId()) {
            throw new \InvalidArgumentException('Recipient must be a user id for the push channel');
        }

        foreach ($this->subscriptions->findForUser(Uuid::fromString($recipient->value())) as $subscription) {
            match ($this->sender->send($subscription, $message)) {
                PushDelivery::Delivered => $this->rememberDelivery($subscription),
                PushDelivery::Gone => $this->subscriptions->delete($subscription),
                PushDelivery::Failed => null,
            };
        }
    }

    public function supports(NotificationChannel $channel): bool
    {
        return $channel->isPush();
    }

    private function rememberDelivery(PushSubscription $subscription): void
    {
        $subscription->markUsed($this->clock->now());
        $this->subscriptions->save($subscription);
    }
}
