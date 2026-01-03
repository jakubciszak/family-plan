<?php

declare(strict_types=1);

namespace App\Notifications\Application\Command;

final readonly class SendNotificationCommand
{
    public function __construct(
        public string $channel,
        public string $recipient,
        public string $message,
        public ?string $subject = null,
        public array $additionalParameters = []
    ) {
    }
}
