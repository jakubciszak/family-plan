<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\ValueObject;

final readonly class PreferenceType
{
    private const NOTIFICATIONS = 'notifications';
    /** Which notification events a user wants at all, e.g. task_assigned => false. */
    private const NOTIFICATION_EVENTS = 'notification_events';
    private const ALLOWED_TYPES = [
        self::NOTIFICATIONS,
        self::NOTIFICATION_EVENTS,
    ];

    private function __construct(
        private string $value
    ) {
    }

    public static function notifications(): self
    {
        return new self(self::NOTIFICATIONS);
    }

    public static function notificationEvents(): self
    {
        return new self(self::NOTIFICATION_EVENTS);
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid preference type: {$value}");
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(PreferenceType $other): bool
    {
        return $this->value === $other->value;
    }
}
