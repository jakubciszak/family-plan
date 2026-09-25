<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Port;

use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\PushOptions;

interface PushSenderInterface
{
    public function send(PushSubscription $subscription, NotificationMessage $message, ?PushOptions $options = null): PushDelivery;
}
