<?php

declare(strict_types=1);

namespace App\DayPlanning\Application\Query;

use App\DayPlanning\Domain\Exception\PlanningException;
use App\DayPlanning\Domain\ValueObject\EventSchedule;
use App\DayPlanning\Domain\ValueObject\QueryRange;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class PlanningView
{
    public function __construct(private CalendarView $calendar)
    {
    }

    public function suggestions(Uuid $caller, array $query): array
    {
        $availability = $this->calendar->availability($caller, $query);
        $range = QueryRange::fromStrings($query['from'] ?? null, $query['to'] ?? null);
        $duration = $query['durationMinutes'] ?? null;
        $windowStart = $query['windowStart'] ?? '08:00';
        $windowEnd = $query['windowEnd'] ?? '20:00';
        $zone = $query['timeZone'] ?? null;
        if (!is_int($duration) || $duration < 15 || $duration > 1440 || !is_string($zone) || !in_array($zone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true) || !is_string($windowStart) || !is_string($windowEnd) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $windowStart) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $windowEnd) || $windowEnd <= $windowStart) {
            throw PlanningException::invalid('Invalid duration, time zone or daily planning window.');
        }
        $timezone = new \DateTimeZone($zone);
        $firstDate = $range->from->setTimezone($timezone)->format('Y-m-d');
        $lastDate = $range->to->setTimezone($timezone)->format('Y-m-d');
        $slots = [];
        $busy = array_map(static fn (array $item): array => [new \DateTimeImmutable($item['start']), new \DateTimeImmutable($item['end'])], $availability['busy']);
        for ($day = EventSchedule::date($firstDate); $day->format('Y-m-d') <= $lastDate && count($slots) < 20; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            for ($minute = 0; $minute < 1440 && count($slots) < 20; $minute += 15) {
                $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
                if ($time < $windowStart || $time >= $windowEnd) {
                    continue;
                }
                try {
                    $start = EventSchedule::localInstant($date.'T'.$time, $zone);
                } catch (PlanningException) {
                    continue;
                }
                $end = $start->modify('+'.$duration.' minutes');
                $localEnd = $end->setTimezone($timezone);
                if ($start < $range->from || $end > $range->to || $localEnd->format('Y-m-d') !== $date || $localEnd->format('H:i') > $windowEnd) {
                    continue;
                }
                foreach ($busy as [$busyStart, $busyEnd]) {
                    if ($busyStart < $end && $busyEnd > $start) {
                        continue 2;
                    }
                }
                $slots[] = ['start' => $start->format(DATE_ATOM), 'end' => $end->format(DATE_ATOM)];
            }
        }
        return ['slots' => $slots] + $availability;
    }
}
