<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

final class PointsStreak
{
    /**
     * @param array<string, int> $perDay
     * @return string[] the days of the run that ends on the most recent qualifying day
     */
    public static function current(array $perDay, int $pointsPerDay = 1): array
    {
        $days = DailyPoints::daysReaching($perDay, $pointsPerDay);

        if ($days === []) {
            return [];
        }

        $run = [array_pop($days)];

        while ($days !== []) {
            $candidate = array_pop($days);

            if (DailyPoints::day($candidate)->diff(DailyPoints::day($run[0]))->days !== 1) {
                break;
            }

            array_unshift($run, $candidate);
        }

        return $run;
    }

    /**
     * @param array<string, int> $perDay
     * @return string[] the run that is still alive on the given day, empty when it has been broken
     */
    public static function aliveOn(array $perDay, string $asOf, int $pointsPerDay = 1): array
    {
        $run = self::current($perDay, $pointsPerDay);

        if ($run === []) {
            return [];
        }

        $last = DailyPoints::day((string) end($run));

        return $last->diff(DailyPoints::day($asOf))->days <= 1 ? $run : [];
    }
}
