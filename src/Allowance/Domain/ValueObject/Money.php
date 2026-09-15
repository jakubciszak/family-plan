<?php

declare(strict_types=1);

namespace App\Allowance\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(private int $minorUnits)
    {
    }

    public static function fromMinorUnits(int $minorUnits): self
    {
        return new self($minorUnits);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function plus(self $other): self
    {
        return new self($this->minorUnits + $other->minorUnits);
    }

    public function minus(self $other): self
    {
        return new self($this->minorUnits - $other->minorUnits);
    }

    public function negated(): self
    {
        return new self(-$this->minorUnits);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->minorUnits > $other->minorUnits;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits;
    }

    public function assertPositive(string $what): void
    {
        if ($this->minorUnits <= 0) {
            throw new InvalidArgumentException(sprintf('%s must be more than zero', $what));
        }
    }
}
