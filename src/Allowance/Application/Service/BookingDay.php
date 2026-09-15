<?php

declare(strict_types=1);

namespace App\Allowance\Application\Service;

use DateTimeImmutable;
use InvalidArgumentException;

final class BookingDay
{
    public static function from(?string $day): ?DateTimeImmutable
    {
        if ($day === null || $day === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $day);

        if ($parsed === false) {
            throw new InvalidArgumentException('A day is given as YYYY-MM-DD');
        }

        return $parsed->setTime(12, 0);
    }
}
