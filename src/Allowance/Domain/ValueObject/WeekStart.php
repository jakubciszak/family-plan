<?php

declare(strict_types=1);

namespace App\Allowance\Domain\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class WeekStart
{
    private function __construct(private DateTimeImmutable $monday)
    {
    }

    public static function of(DateTimeImmutable $day): self
    {
        return new self($day->modify('monday this week')->setTime(0, 0));
    }

    public static function fromString(string $day): self
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $day);

        if ($parsed === false) {
            throw new InvalidArgumentException('A week is asked for by any day in it, given as YYYY-MM-DD');
        }

        return self::of($parsed);
    }

    public function monday(): DateTimeImmutable
    {
        return $this->monday;
    }

    public function nextMonday(): DateTimeImmutable
    {
        return $this->monday->modify('+7 days');
    }

    public function covers(DateTimeImmutable $day): bool
    {
        return $day >= $this->monday && $day < $this->nextMonday();
    }

    public function value(): string
    {
        return $this->monday->format('Y-m-d');
    }

    public function equals(self $other): bool
    {
        return $this->value() === $other->value();
    }
}
