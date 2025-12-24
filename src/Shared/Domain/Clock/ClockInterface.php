<?php

declare(strict_types=1);

namespace App\Shared\Domain\Clock;

use DateTimeImmutable;

/**
 * PSR-20 compatible Clock interface for dependency injection of time
 * Allows testing with fixed time and production with system time
 */
interface ClockInterface
{
    /**
     * Returns the current time as a DateTimeImmutable object.
     */
    public function now(): DateTimeImmutable;
}
