<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\ValueObject;

use InvalidArgumentException;

final class TeamName
{
    private const MIN_LENGTH = 1;
    private const MAX_LENGTH = 255;

    private function __construct(
        private readonly string $value
    ) {
        $this->validate();
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    private function validate(): void
    {
        $length = mb_strlen($this->value);
        
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf(
                    'Team name must be between %d and %d characters',
                    self::MIN_LENGTH,
                    self::MAX_LENGTH
                )
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
