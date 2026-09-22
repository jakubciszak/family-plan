<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Application\Service;

use App\SchoolTimetable\Domain\Exception\TimetableException;

final readonly class ImportWeek
{
    public static function from(mixed $value): string
    {
        if ($value === null) {
            return self::monday(new \DateTimeImmutable('today'));
        }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw TimetableException::invalid('A week starts on a date in the YYYY-MM-DD format.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw TimetableException::invalid('Invalid week.');
        }

        return self::monday($date);
    }

    public static function end(string $weekStart): string
    {
        return (new \DateTimeImmutable($weekStart))->modify('+6 days')->format('Y-m-d');
    }

    private static function monday(\DateTimeImmutable $date): string
    {
        return $date->modify('monday this week')->format('Y-m-d');
    }
}
