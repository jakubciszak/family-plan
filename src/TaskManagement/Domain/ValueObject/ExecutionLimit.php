<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ExecutionLimit
{
    public const UNLIMITED = 'unlimited';
    public const ONCE = 'once';
    public const PER_DAY = 'per_day';
    public const PER_WEEK = 'per_week';
    public const PER_MONTH = 'per_month';

    private function __construct(
        public string $type,
        public ?int $count = null
    ) {
        if (!in_array($type, [self::UNLIMITED, self::ONCE, self::PER_DAY, self::PER_WEEK, self::PER_MONTH], true)) {
            throw new InvalidArgumentException(sprintf('Unknown execution limit: %s', $type));
        }

        if (in_array($type, [self::PER_DAY, self::PER_WEEK, self::PER_MONTH], true) && ($count === null || $count < 1)) {
            throw new InvalidArgumentException('A windowed execution limit needs a count of at least 1');
        }
    }

    public static function unlimited(): self
    {
        return new self(self::UNLIMITED);
    }

    public static function once(): self
    {
        return new self(self::ONCE);
    }

    public static function perDay(int $count): self
    {
        return new self(self::PER_DAY, $count);
    }

    public static function perWeek(int $count): self
    {
        return new self(self::PER_WEEK, $count);
    }

    public static function perMonth(int $count): self
    {
        return new self(self::PER_MONTH, $count);
    }

    public function allowsAnother(int $alreadyTaken): bool
    {
        $remaining = $this->remaining($alreadyTaken);

        return $remaining === null || $remaining > 0;
    }

    public function remaining(int $alreadyTaken): ?int
    {
        if ($this->type === self::UNLIMITED) {
            return null;
        }

        $cap = $this->type === self::ONCE ? 1 : $this->count;

        return max(0, $cap - $alreadyTaken);
    }

    public function windowStart(DateTimeImmutable $now): ?DateTimeImmutable
    {
        return match ($this->type) {
            self::PER_DAY => $now->setTime(0, 0),
            self::PER_WEEK => $now->modify('monday this week')->setTime(0, 0),
            self::PER_MONTH => $now->modify('first day of this month')->setTime(0, 0),
            default => null,
        };
    }

    public function toArray(): array
    {
        return array_filter(
            ['type' => $this->type, 'count' => $this->count],
            static fn ($value) => $value !== null
        );
    }

    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? self::UNLIMITED;
        $count = isset($data['count']) ? (int) $data['count'] : null;

        return match ($type) {
            self::ONCE => self::once(),
            self::PER_DAY => self::perDay((int) $count),
            self::PER_WEEK => self::perWeek((int) $count),
            self::PER_MONTH => self::perMonth((int) $count),
            default => self::unlimited(),
        };
    }
}
