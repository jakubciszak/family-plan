<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Clock;

use App\Shared\Domain\Clock\ClockInterface;
use DateTimeImmutable;

/**
 * Fixed clock implementation that always returns the same time
 * Used in tests for deterministic behavior
 */
final class FixedClock implements ClockInterface
{
    private DateTimeImmutable $fixedTime;

    public function __construct(?DateTimeImmutable $fixedTime = null)
    {
        $this->fixedTime = $fixedTime ?? new DateTimeImmutable('2025-01-01 00:00:00');
    }

    public function now(): DateTimeImmutable
    {
        return $this->fixedTime;
    }

    public function setTime(DateTimeImmutable $time): void
    {
        $this->fixedTime = $time;
    }
}
