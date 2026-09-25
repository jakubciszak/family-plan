<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Port;

use App\Notifications\Domain\Entity\NativePushDevice;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use App\Notifications\Domain\ValueObject\PushOptions;

interface NativePushSenderInterface
{
    public function isConfigured(): bool;

    public function send(NativePushDevice $device, NotificationMessage $message, PushOptions $options): PushDelivery;
}
