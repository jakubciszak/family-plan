<?php

declare(strict_types=1);

namespace App\UserManagement\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Email
{
    private function __construct(
        private string $value
    ) {
        $this->validate($value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private function validate(string $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(sprintf('Invalid email format: %s', $value));
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
