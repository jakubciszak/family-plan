<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Mother;

use App\Notifications\Domain\ValueObject\NotificationMessage;

final class NotificationMessageMother
{
    public static function create(
        ?string $content = null,
        ?string $subject = null,
        array $additionalParameters = []
    ): NotificationMessage {
        return NotificationMessage::create(
            $content ?? self::randomContent(),
            $subject,
            $additionalParameters
        );
    }

    public static function email(?string $subject = null): NotificationMessage
    {
        return NotificationMessage::create(
            self::randomContent(),
            $subject ?? self::randomSubject()
        );
    }

    public static function sms(): NotificationMessage
    {
        return NotificationMessage::create(
            self::randomSmsContent()
        );
    }

    public static function withParameters(array $parameters): NotificationMessage
    {
        return NotificationMessage::create(
            self::randomContent(),
            self::randomSubject(),
            $parameters
        );
    }

    private static function randomContent(): string
    {
        $messages = [
            'Your task has been approved',
            'New task assigned to you',
            'Weekly report is ready',
            'Important notification',
        ];
        
        return $messages[array_rand($messages)];
    }

    private static function randomSubject(): string
    {
        $subjects = [
            'Task Notification',
            'System Update',
            'Weekly Report',
            'Important Message',
        ];
        
        return $subjects[array_rand($subjects)];
    }

    private static function randomSmsContent(): string
    {
        $messages = [
            'Task approved!',
            'New task assigned',
            'Check your app',
            'Action required',
        ];
        
        return $messages[array_rand($messages)];
    }
}
