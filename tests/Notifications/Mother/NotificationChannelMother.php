<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Mother;

use App\Notifications\Domain\ValueObject\NotificationChannel;

final class NotificationChannelMother
{
    public static function email(): NotificationChannel
    {
        return NotificationChannel::email();
    }

    public static function sms(): NotificationChannel
    {
        return NotificationChannel::sms();
    }

    public static function random(): NotificationChannel
    {
        $channels = ['email', 'sms'];
        $randomChannel = $channels[array_rand($channels)];
        
        return NotificationChannel::fromString($randomChannel);
    }
}
