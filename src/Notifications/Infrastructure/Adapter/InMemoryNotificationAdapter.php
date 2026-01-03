<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Adapter;

use App\Notifications\Domain\Port\NotificationPortInterface;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;

final class InMemoryNotificationAdapter implements NotificationPortInterface
{
    private array $sentNotifications = [];

    public function send(
        Recipient $recipient,
        NotificationMessage $message,
        NotificationChannel $channel
    ): void {
        $this->sentNotifications[] = [
            'recipient' => $recipient->value(),
            'message' => $message->content(),
            'subject' => $message->subject(),
            'channel' => $channel->value(),
            'parameters' => $message->additionalParameters(),
            'timestamp' => time(),
        ];
    }

    public function supports(NotificationChannel $channel): bool
    {
        return true;
    }

    public function getSentNotifications(): array
    {
        return $this->sentNotifications;
    }

    public function clear(): void
    {
        $this->sentNotifications = [];
    }
}
