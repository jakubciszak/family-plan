<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Adapter;

use App\Notifications\Domain\Port\NotificationPortInterface;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use Psr\Log\LoggerInterface;

final readonly class SmsNotificationAdapter implements NotificationPortInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null
    ) {
    }

    public function send(
        Recipient $recipient,
        NotificationMessage $message,
        NotificationChannel $channel
    ): void {
        if (!$channel->isSms()) {
            throw new \InvalidArgumentException('SmsNotificationAdapter only supports SMS channel');
        }

        if (!$recipient->isPhoneNumber()) {
            throw new \InvalidArgumentException('Recipient must be a phone number for SMS channel');
        }

        // In a real implementation, this would send SMS using a service like Twilio
        // For now, we just log it
        $this->logger?->info('Sending SMS notification', [
            'recipient' => $recipient->value(),
            'content' => $message->content(),
            'parameters' => $message->additionalParameters(),
        ]);

        // TODO: Implement actual SMS sending using Twilio, Vonage, or similar
    }

    public function supports(NotificationChannel $channel): bool
    {
        return $channel->isSms();
    }
}
