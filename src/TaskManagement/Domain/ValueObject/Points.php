<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Points
{
    private function __construct(
        private int $value
    ) {
        $this->validate($value);
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private function validate(int $value): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Points cannot be negative');
        }

        if ($value > 1000) {
            throw new InvalidArgumentException('Points cannot exceed 1000');
        }
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
