<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use DateTimeImmutable;

final class StreakAtRisk
{
    /**
     * A streak is at risk when it ran up to yesterday and today has not reached the threshold yet.
     *
     * @param array<string, int> $counted points the rule counts on each day, keyed by Y-m-d
     * @return int how many days would be lost, 0 when there is nothing to lose
     */
    public static function days(array $counted, int $pointsPerDay, DateTimeImmutable $today, ?int $requiredDays = null): int
    {
        if (($counted[$today->format('Y-m-d')] ?? 0) >= $pointsPerDay) {
            return 0;
        }

        $run = PointsStreak::current($counted, $pointsPerDay);

        if ($run === []) {
            return 0;
        }

        $yesterday = $today->modify('-1 day')->format('Y-m-d');

        $days = $requiredDays === null ? count($run) : count($run) % $requiredDays;

        return end($run) === $yesterday ? $days : 0;
    }
}
