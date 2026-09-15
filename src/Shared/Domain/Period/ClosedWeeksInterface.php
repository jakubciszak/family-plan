<?php

declare(strict_types=1);

namespace App\Shared\Domain\Period;

use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Weeks that have been settled and can no longer take new bookings.
 */
interface ClosedWeeksInterface
{
    public function isClosedFor(Uuid $userId, DateTimeImmutable $day): bool;
}
