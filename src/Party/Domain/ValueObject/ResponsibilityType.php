<?php

declare(strict_types=1);

namespace App\Party\Domain\ValueObject;

use InvalidArgumentException;

final readonly class ResponsibilityType
{
    public const DEFINE_TASK_TYPE = 'DEFINE_TASK_TYPE';
    public const TAKE_TASK = 'TAKE_TASK';
    public const ASSIGN_TASK = 'ASSIGN_TASK';
    public const COMPLETE_TASK = 'COMPLETE_TASK';
    public const APPROVE_TASK = 'APPROVE_TASK';

    private const VALID_TYPES = [
        self::DEFINE_TASK_TYPE,
        self::TAKE_TASK,
        self::ASSIGN_TASK,
        self::COMPLETE_TASK,
        self::APPROVE_TASK,
    ];

    private function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid responsibility type: %s', $value));
        }
    }

    public static function defineTaskType(): self
    {
        return new self(self::DEFINE_TASK_TYPE);
    }

    public static function takeTask(): self
    {
        return new self(self::TAKE_TASK);
    }

    public static function assignTask(): self
    {
        return new self(self::ASSIGN_TASK);
    }

    public static function completeTask(): self
    {
        return new self(self::COMPLETE_TASK);
    }

    public static function approveTask(): self
    {
        return new self(self::APPROVE_TASK);
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

    public function __toString(): string
    {
        return $this->value;
    }
}
