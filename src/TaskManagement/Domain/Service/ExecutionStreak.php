<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\TaskManagement\Domain\Entity\TaskExecution;

final class ExecutionStreak
{
    /**
     * @param TaskExecution[] $executions
     */
    public static function longest(array $executions, int $pointsPerDay = 1): int
    {
        $days = DailyPoints::daysReaching($executions, $pointsPerDay);

        if ($days === []) {
            return 0;
        }

        $longest = 1;
        $current = 1;

        for ($index = 1; $index < count($days); $index++) {
            $previous = DailyPoints::day($days[$index - 1]);
            $day = DailyPoints::day($days[$index]);

            if ($previous->diff($day)->days === 1) {
                $current++;
                $longest = max($longest, $current);

                continue;
            }

            $current = 1;
        }

        return $longest;
    }

    /**
     * @param TaskExecution[] $executions
     * @return string[] the days of the run that ends on the most recent qualifying day
     */
    public static function current(array $executions, int $pointsPerDay = 1): array
    {
        $days = DailyPoints::daysReaching($executions, $pointsPerDay);

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
}
