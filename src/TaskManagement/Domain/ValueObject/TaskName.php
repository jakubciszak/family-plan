<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\ValueObject;

use InvalidArgumentException;

final readonly class TaskName
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
        if (empty(trim($value))) {
            throw new InvalidArgumentException('Task name cannot be empty');
        }

        if (strlen($value) > 255) {
            throw new InvalidArgumentException('Task name cannot exceed 255 characters');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
