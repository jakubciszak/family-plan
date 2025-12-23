<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Clock;

use App\Shared\Domain\Clock\ClockInterface;
use DateTimeImmutable;

/**
 * System clock implementation that returns the current system time
 * Used in production
 */
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
