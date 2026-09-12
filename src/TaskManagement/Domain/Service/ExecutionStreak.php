<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Service;

use App\TaskManagement\Domain\Entity\TaskExecution;

final class ExecutionStreak
{
    /**
     * @param TaskExecution[] $executions
     */
    public static function longest(array $executions): int
    {
        if ($executions === []) {
            return 0;
        }

        $days = self::distinctDaysNewestFirst($executions);

        $longest = 1;
        $current = 1;

        for ($index = 1; $index < count($days); $index++) {
            $difference = $days[$index - 1]->diff($days[$index]);

            if ($difference->days === 1) {
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
     * @return \DateTimeImmutable[]
     */
    private static function distinctDaysNewestFirst(array $executions): array
    {
        $days = [];

        foreach ($executions as $execution) {
            $day = $execution->scheduledFor()->setTime(0, 0);
            $days[$day->format('Y-m-d')] = $day;
        }

        krsort($days);

        return array_values($days);
    }
}
