<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Port;

use App\Notifications\Domain\ValueObject\NotificationChannel;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\Recipient;

interface NotificationPortInterface
{
    public function send(
        Recipient $recipient,
        NotificationMessage $message,
        NotificationChannel $channel
    ): void;

    public function supports(NotificationChannel $channel): bool;
}
