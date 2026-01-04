<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Adapter;

use App\Notifications\Domain\Port\NotificationPortInterface;
use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class EmailNotificationAdapter implements NotificationPortInterface
{
    public function __construct(
        private ?MailerInterface $mailer = null,
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

        // Log the notification
        $this->logger?->info('Sending email notification', [
            'recipient' => $recipient->value(),
            'subject' => $message->subject(),
            'content' => $message->content(),
            'parameters' => $message->additionalParameters(),
        ]);

        // Send actual email if mailer is available
        if ($this->mailer !== null) {
            $email = (new Email())
                ->to($recipient->value())
                ->subject($message->subject() ?? 'Notification')
                ->html($message->content());

            $this->mailer->send($email);
        }
    }

    public function supports(NotificationChannel $channel): bool
    {
        return $channel->isEmail();
    }
}
