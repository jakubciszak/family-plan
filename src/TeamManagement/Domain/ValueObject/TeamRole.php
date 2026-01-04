<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\ValueObject;

use InvalidArgumentException;

final class TeamRole
{
    private const ADMIN = 'admin';
    private const MEMBER = 'member';

    private function __construct(
        public readonly string $value
    ) {
        $this->validate();
    }

    public static function admin(): self
    {
        return new self(self::ADMIN);
    }

    public static function member(): self
    {
        return new self(self::MEMBER);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function isAdmin(): bool
    {
        return $this->value === self::ADMIN;
    }

    public function isMember(): bool
    {
        return $this->value === self::MEMBER;
    }

    public function value(): string
    {
        return $this->value;
    }

    private function validate(): void
    {
        if (!in_array($this->value, [self::ADMIN, self::MEMBER], true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid team role: %s', $this->value)
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
