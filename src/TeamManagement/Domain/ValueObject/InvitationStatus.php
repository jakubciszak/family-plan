<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\ValueObject;

use InvalidArgumentException;

final class InvitationStatus
{
    private const PENDING = 'pending';
    private const ACCEPTED = 'accepted';
    private const REJECTED = 'rejected';
    private const EXPIRED = 'expired';

    private function __construct(
        public readonly string $value
    ) {
        $this->validate();
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function accepted(): self
    {
        return new self(self::ACCEPTED);
    }

    public static function rejected(): self
    {
        return new self(self::REJECTED);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->value === self::ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->value === self::REJECTED;
    }

    public function isExpired(): bool
    {
        return $this->value === self::EXPIRED;
    }

    private function validate(): void
    {
        if (!in_array($this->value, [self::PENDING, self::ACCEPTED, self::REJECTED, self::EXPIRED], true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid invitation status: %s', $this->value)
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
