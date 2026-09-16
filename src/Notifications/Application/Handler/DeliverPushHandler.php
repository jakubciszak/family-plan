<?php

declare(strict_types=1);

namespace App\Notifications\Application\Handler;

use App\Notifications\Application\Command\DeliverPushCommand;
use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\Port\PushDelivery;
use App\Notifications\Domain\Port\PushSenderInterface;
use App\Notifications\Domain\Repository\PushSubscriptionRepositoryInterface;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeliverPushHandler
{
    public function __construct(
        private PushSubscriptionRepositoryInterface $subscriptions,
        private PushSenderInterface $sender,
        private ClockInterface $clock
    ) {
    }

    public function __invoke(DeliverPushCommand $command): void
    {
        $message = NotificationMessage::create(
            $command->message,
            $command->subject,
            $command->additionalParameters
        );

        foreach ($this->subscriptions->findForUser(Uuid::fromString($command->userId)) as $subscription) {
            match ($this->sender->send($subscription, $message)) {
                PushDelivery::Delivered => $this->rememberDelivery($subscription),
                PushDelivery::Gone => $this->subscriptions->delete($subscription),
                PushDelivery::Failed => null,
            };
        }
    }

    private function rememberDelivery(PushSubscription $subscription): void
    {
        $subscription->markUsed($this->clock->now());
        $this->subscriptions->save($subscription);
    }
}
