<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\Service;

final class BusyIntervals
{
    public function merge(array $intervals): array
    {
        usort($intervals, static fn (array $a, array $b): int => [$a['personId'], $a['start'], $a['end']] <=> [$b['personId'], $b['start'], $b['end']]);
        $merged = [];
        foreach ($intervals as $interval) {
            $last = array_key_last($merged);
            if ($last !== null && $merged[$last]['personId'] === $interval['personId'] && $merged[$last]['end'] >= $interval['start']) {
                $merged[$last]['end'] = max($merged[$last]['end'], $interval['end']);
            } else {
                $merged[] = ['kind' => 'busy', 'personId' => $interval['personId'], 'start' => $interval['start'], 'end' => $interval['end']];
            }
        }
        return $merged;
    }
}
