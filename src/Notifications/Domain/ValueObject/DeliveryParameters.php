<?php

declare(strict_types=1);

namespace App\Notifications\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Parameters that steer how a notification travels rather than what it says.
 */
final class DeliveryParameters
{
    /** Id of the in-app notification the push copies, so the worker can tell it got handled meanwhile. */
    public const NOTIFICATION_ID = 'notification_id';
    /** Seconds a push service may hold the message for a device that is offline. */
    public const TTL = 'ttl';
    /** When the push entered the queue (ATOM). */
    public const QUEUED_AT = 'queued_at';
    public const URGENCY = 'urgency';
    /** Moment after which the notification is no longer true (ATOM). */
    public const EXPIRES_AT = 'expires_at';

    private const INTERNAL = [self::NOTIFICATION_ID, self::TTL, self::QUEUED_AT, self::URGENCY];

    private function __construct()
    {
    }

    public static function notificationId(array $parameters): ?Uuid
    {
        $id = $parameters[self::NOTIFICATION_ID] ?? null;

        return is_string($id) && Uuid::isValid($id) ? Uuid::fromString($id) : null;
    }

    public static function expiresAt(array $parameters): ?DateTimeImmutable
    {
        return self::moment($parameters[self::EXPIRES_AT] ?? null);
    }

    public static function queuedAt(array $parameters): ?DateTimeImmutable
    {
        return self::moment($parameters[self::QUEUED_AT] ?? null);
    }

    public static function ttl(array $parameters): ?int
    {
        $ttl = $parameters[self::TTL] ?? null;

        return is_int($ttl) && $ttl > 0 ? $ttl : null;
    }

    /**
     * What the recipient may see: everything except the delivery bookkeeping.
     */
    public static function withoutDeliveryDetails(array $parameters): array
    {
        return array_diff_key($parameters, array_flip(self::INTERNAL));
    }

    private static function moment(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $moment = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);

        return $moment === false ? null : $moment;
    }
}
