<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object representing points balance
 */
final readonly class PointsBalance
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

    public static function zero(): self
    {
        return new self(0);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function add(int $points): self
    {
        if ($points < 0) {
            throw new InvalidArgumentException('Cannot add negative points');
        }
        
        return new self($this->value + $points);
    }

    public function subtract(int $points): self
    {
        if ($points < 0) {
            throw new InvalidArgumentException('Cannot subtract negative points');
        }
        
        $newBalance = $this->value - $points;
        if ($newBalance < 0) {
            throw new \DomainException('Insufficient points balance');
        }
        
        return new self($newBalance);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private function validate(int $value): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Points balance cannot be negative');
        }
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
