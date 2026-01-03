<?php

declare(strict_types=1);

namespace App\Notifications\Application\Handler;

use App\Notifications\Application\Command\SendNotificationCommand;
use App\Notifications\Domain\Port\NotificationPortInterface;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SendNotificationHandler
{
    /**
     * @param NotificationPortInterface[] $adapters
     */
    public function __construct(
        private iterable $adapters
    ) {
    }

    public function __invoke(SendNotificationCommand $command): void
    {
        $channel = NotificationChannel::fromString($command->channel);
        $message = NotificationMessage::create(
            $command->message,
            $command->subject,
            $command->additionalParameters
        );

        $recipient = $this->createRecipient($command->recipient, $channel);

        $adapter = $this->findAdapter($channel);
        $adapter->send($recipient, $message, $channel);
    }

    private function createRecipient(string $recipientValue, NotificationChannel $channel): Recipient
    {
        return match (true) {
            $channel->isEmail() => Recipient::email($recipientValue),
            $channel->isSms() => Recipient::phoneNumber($recipientValue),
            default => throw new \InvalidArgumentException("Unsupported channel type: {$channel->value()}")
        };
    }

    private function findAdapter(NotificationChannel $channel): NotificationPortInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($channel)) {
                return $adapter;
            }
        }

        throw new \RuntimeException("No adapter found for channel: {$channel->value()}");
    }
}
