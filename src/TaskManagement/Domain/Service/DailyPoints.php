<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\TaskManagement\Domain\Entity\TaskExecution;
use DateTimeImmutable;

final class DailyPoints
{
    /**
     * @param TaskExecution[] $executions
     * @return array<string, int> points earned per day, keyed by Y-m-d, oldest first
     */
    public static function perDay(array $executions): array
    {
        $points = [];

        foreach ($executions as $execution) {
            $day = $execution->earnedOn()->format('Y-m-d');
            $points[$day] = ($points[$day] ?? 0) + ($execution->points()?->value() ?? 0);
        }

        ksort($points);

        return $points;
    }

    /**
     * @param array<string, int> $perDay
     * @return string[] days that reached the threshold, oldest first
     */
    public static function daysReaching(array $perDay, int $pointsPerDay): array
    {
        return array_keys(array_filter(
            $perDay,
            static fn (int $points) => $points >= $pointsPerDay
        ));
    }

    public static function day(string $day): DateTimeImmutable
    {
        return new DateTimeImmutable($day . ' 00:00:00');
    }
}
