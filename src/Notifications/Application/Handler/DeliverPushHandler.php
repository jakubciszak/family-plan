<?php

declare(strict_types=1);

namespace App\Notifications\Application\Handler;

use App\Notifications\Application\Command\DeliverPushCommand;
use App\Notifications\Application\Service\CalendarNotificationAccess;
use App\Notifications\Domain\Entity\NativePushDevice;
use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\Port\NativePushSenderInterface;
use App\Notifications\Domain\Port\PushDelivery;
use App\Notifications\Domain\Port\PushSenderInterface;
use App\Notifications\Domain\Repository\InAppNotificationRepositoryInterface;
use App\Notifications\Domain\Repository\NativePushDeviceRepositoryInterface;
use App\Notifications\Domain\Repository\PushSubscriptionRepositoryInterface;
use App\Notifications\Domain\ValueObject\DeliveryParameters;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\PushOptions;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeliverPushHandler
{
    public function __construct(
        private PushSubscriptionRepositoryInterface $subscriptions,
        private PushSenderInterface $sender,
        private ClockInterface $clock,
        private ?CalendarNotificationAccess $calendarAccess = null,
        private ?InAppNotificationRepositoryInterface $inApp = null,
        private ?NativePushDeviceRepositoryInterface $devices = null,
        private ?NativePushSenderInterface $nativeSender = null
    ) {
    }

    public function __invoke(DeliverPushCommand $command): void
    {
        if (isset($command->additionalParameters['_calendar']) && ($this->calendarAccess === null || !$this->calendarAccess->allows($command->userId, $command->additionalParameters))) {
            return;
        }

        $now = $this->clock->now();

        if ($this->nothingLeftToSay($command->additionalParameters, $now)) {
            return;
        }

        $message = NotificationMessage::create(
            $command->message,
            $command->subject,
            CalendarNotificationAccess::publicParameters($command->additionalParameters)
        );
        $options = PushOptions::from($command->additionalParameters, $now);
        $userId = Uuid::fromString($command->userId);

        foreach ($this->subscriptions->findForUser($userId) as $subscription) {
            match ($this->sender->send($subscription, $message, $options)) {
                PushDelivery::Delivered => $this->rememberDelivery($subscription),
                PushDelivery::Gone => $this->subscriptions->delete($subscription),
                PushDelivery::Failed => null,
            };
        }

        if ($this->devices === null || $this->nativeSender === null || !$this->nativeSender->isConfigured()) {
            return;
        }

        foreach ($this->devices->findForUser($userId) as $device) {
            match ($this->nativeSender->send($device, $message, $options)) {
                PushDelivery::Delivered => $this->rememberDeviceDelivery($device),
                PushDelivery::Gone => $this->devices->delete($device),
                PushDelivery::Failed => null,
            };
        }
    }

    /**
     * The queue may run late. By then the approval can be done, the notification read or the warning
     * out of date, and a push would only repeat something that is no longer true.
     */
    private function nothingLeftToSay(array $parameters, \DateTimeImmutable $now): bool
    {
        if (PushOptions::isOutdated($parameters, $now)) {
            return true;
        }

        $id = DeliveryParameters::notificationId($parameters);
        $notification = $id === null ? null : $this->inApp?->findById($id);

        return $notification !== null && !$notification->isActive($now);
    }

    private function rememberDelivery(PushSubscription $subscription): void
    {
        $subscription->markUsed($this->clock->now());
        $this->subscriptions->save($subscription);
    }

    private function rememberDeviceDelivery(NativePushDevice $device): void
    {
        $device->markUsed($this->clock->now());
        $this->devices?->save($device);
    }
}
