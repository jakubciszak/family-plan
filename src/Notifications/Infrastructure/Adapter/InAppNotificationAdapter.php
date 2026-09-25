<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Adapter;

use App\Notifications\Domain\Entity\InAppNotification;
use App\Notifications\Application\Service\CalendarNotificationAccess;
use App\Notifications\Domain\Port\NotificationPortInterface;
use App\Notifications\Domain\Port\PushRetractionInterface;
use App\Notifications\Domain\Repository\InAppNotificationRepositoryInterface;
use App\Notifications\Domain\ValueObject\DeliveryParameters;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class InAppNotificationAdapter implements NotificationPortInterface
{
    public function __construct(
        private InAppNotificationRepositoryInterface $notifications,
        private ClockInterface $clock,
        private ?PushRetractionInterface $retraction = null
    ) {
    }

    public function send(
        Recipient $recipient,
        NotificationMessage $message,
        NotificationChannel $channel
    ): void {
        if (!$channel->isInApp()) {
            throw new \InvalidArgumentException('InAppNotificationAdapter only supports the in_app channel');
        }

        if (!$recipient->isUserId()) {
            throw new \InvalidArgumentException('Recipient must be a user id for the in_app channel');
        }

        $parameters = $message->additionalParameters();
        $userId = Uuid::fromString($recipient->value());
        $now = $this->clock->now();
        $notification = InAppNotification::raise(
            DeliveryParameters::notificationId($parameters) ?? Uuid::generate(),
            $userId,
            $message->content(),
            $message->subject(),
            DeliveryParameters::withoutDeliveryDetails(CalendarNotificationAccess::publicParameters($parameters)),
            $now
        );

        // The newest word on a topic replaces the older ones, e.g. "approved" after "assigned" for the same task.
        if ($notification->topic() !== null) {
            $replaced = $this->notifications->resolveTopic($notification->topic(), $now, null, $userId);

            // A push with the same tag takes the old one's place in the tray. Without a push the old one would stay.
            if ($replaced !== [] && !self::goesByPush($parameters)) {
                $this->retraction?->retract([$userId], [$notification->topic()]);
            }
        }

        $this->notifications->save($notification);
    }

    private static function goesByPush(array $parameters): bool
    {
        $channels = $parameters['delivery_channels'] ?? [];

        return is_array($channels) && in_array(NotificationChannel::push()->value(), $channels, true);
    }

    public function supports(NotificationChannel $channel): bool
    {
        return $channel->isInApp();
    }
}
