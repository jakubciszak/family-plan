<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Adapter;

use App\Notifications\Domain\Port\NotificationPortInterface;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use Psr\Log\LoggerInterface;

final readonly class EmailNotificationAdapter implements NotificationPortInterface
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
        if (!$channel->isEmail()) {
            throw new \InvalidArgumentException('EmailNotificationAdapter only supports email channel');
        }

        if (!$recipient->isEmail()) {
            throw new \InvalidArgumentException('Recipient must be an email address for email channel');
        }

        // In a real implementation, this would send an email using a mail service
        // For now, we just log it
        $this->logger?->info('Sending email notification', [
            'recipient' => $recipient->value(),
            'subject' => $message->subject(),
            'content' => $message->content(),
            'parameters' => $message->additionalParameters(),
        ]);

        // TODO: Implement actual email sending using Symfony Mailer or similar
    }

    public function supports(NotificationChannel $channel): bool
    {
        return $channel->isEmail();
    }
}
