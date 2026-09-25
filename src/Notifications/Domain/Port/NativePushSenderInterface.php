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

    /**
     * A silent message that makes the app take the notifications with these tags out of the tray.
     *
     * @param list<string> $tags
     */
    public function retract(NativePushDevice $device, array $tags): PushDelivery;
}
