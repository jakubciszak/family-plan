<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Port;

use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\ValueObject\NotificationMessage;

interface PushSenderInterface
{
    public function send(PushSubscription $subscription, NotificationMessage $message): PushDelivery;
}
