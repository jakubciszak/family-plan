<?php

declare(strict_types=1);

namespace App\Notifications\Domain\ValueObject;

final readonly class NotificationChannel
{
    private const EMAIL = 'email';
    private const SMS = 'sms';
    private const IN_APP = 'in_app';
    private const PUSH = 'push';

    private function __construct(
        private string $value
    ) {
    }

    public static function email(): self
    {
        return new self(self::EMAIL);
    }

    public static function sms(): self
    {
        return new self(self::SMS);
    }

    public static function inApp(): self
    {
        return new self(self::IN_APP);
    }

    public static function push(): self
    {
        return new self(self::PUSH);
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, [self::EMAIL, self::SMS, self::IN_APP, self::PUSH], true)) {
            throw new \InvalidArgumentException("Invalid notification channel: {$value}");
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEmail(): bool
    {
        return $this->value === self::EMAIL;
    }

    public function isSms(): bool
    {
        return $this->value === self::SMS;
    }

    public function isInApp(): bool
    {
        return $this->value === self::IN_APP;
    }

    public function isPush(): bool
    {
        return $this->value === self::PUSH;
    }
}
