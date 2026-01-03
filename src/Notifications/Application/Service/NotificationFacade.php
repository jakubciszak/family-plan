<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Application\Command\SendNotificationCommand;
use App\Notifications\Application\Handler\SendNotificationHandler;

/**
 * Facade service for easy integration with other bounded contexts.
 * Provides simple methods for sending notifications without dealing with commands.
 */
final readonly class NotificationFacade
{
    private SendNotificationHandler $handler;

    public function __construct(iterable $adapters)
    {
        $this->handler = new SendNotificationHandler($adapters);
    }

    /**
     * Send an email notification
     *
     * @param string $recipient Email address
     * @param string $message Email body/content
     * @param string|null $subject Email subject (optional)
     * @param array $additionalParameters Additional email parameters (e.g., attachments, cc, bcc)
     */
    public function sendEmail(
        string $recipient,
        string $message,
        ?string $subject = null,
        array $additionalParameters = []
    ): void {
        $this->send('email', $recipient, $message, $subject, $additionalParameters);
    }

    /**
     * Send an SMS notification
     *
     * @param string $recipient Phone number
     * @param string $message SMS content
     * @param array $additionalParameters Additional SMS parameters (e.g., sender name)
     */
    public function sendSms(
        string $recipient,
        string $message,
        array $additionalParameters = []
    ): void {
        $this->send('sms', $recipient, $message, null, $additionalParameters);
    }

    /**
     * Send a notification using any supported channel
     *
     * @param string $channel Communication channel (email, sms)
     * @param string $recipient Recipient address (email or phone number)
     * @param string $message Message content
     * @param string|null $subject Message subject (for email)
     * @param array $additionalParameters Channel-specific parameters
     */
    public function send(
        string $channel,
        string $recipient,
        string $message,
        ?string $subject = null,
        array $additionalParameters = []
    ): void {
        $command = new SendNotificationCommand(
            $channel,
            $recipient,
            $message,
            $subject,
            $additionalParameters
        );

        ($this->handler)($command);
    }
}
