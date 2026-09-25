<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Adapter;

use App\Notifications\Application\Command\DeliverPushCommand;
use App\Notifications\Domain\Port\NotificationPortInterface;
use App\Notifications\Domain\ValueObject\DeliveryParameters;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use App\Shared\Domain\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Hands the delivery over to the command bus: reaching a push service takes a round trip
 * per device, and nobody should wait for that while a request is open.
 */
final readonly class PushNotificationAdapter implements NotificationPortInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private ?ClockInterface $clock = null
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

        $parameters = $message->additionalParameters();
        $parameters[DeliveryParameters::QUEUED_AT] = ($this->clock?->now() ?? new \DateTimeImmutable())->format(DATE_ATOM);

        $this->commandBus->dispatch(new DeliverPushCommand(
            $recipient->value(),
            $message->content(),
            $message->subject(),
            $parameters
        ));
    }

    public function supports(NotificationChannel $channel): bool
    {
        return $channel->isPush();
    }
}
