<?php

declare(strict_types=1);

namespace App\DayPlanning\Application\Service;

use App\DayPlanning\Application\Query\CalendarView;
use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Service\BusyIntervals;
use App\DayPlanning\Domain\Service\OccurrenceExpander;
use App\DayPlanning\Domain\ValueObject\EventSchedule;
use App\DayPlanning\Domain\ValueObject\QueryRange;

final readonly class ConflictDetector
{
    public function __construct(private CalendarView $calendar, private OccurrenceExpander $expander, private PlanningAccess $access, private BusyIntervals $intervals)
    {
    }

    private function ranges(CalendarEvent $candidate, ?string $onlyKey = null): array
    {
        $schedule = EventSchedule::fromArray($candidate->definition()['schedule']);
        [$start, $end] = $schedule->atDate($schedule->anchorDate());
        $ranges = [];
        if ($onlyKey !== null) {
            $occurrence = $this->expander->occurrence($candidate, $onlyKey);
            if ($occurrence === null) {
                return [];
            }
            $ranges = $this->splitRange($occurrence->start, $occurrence->end);
        } elseif ($candidate->definition()['recurrence'] === null) {
            $ranges = $this->splitRange($start, $end);
        } else {
            $ranges[] = QueryRange::fromStrings($start->format(DATE_ATOM), $start->modify('+90 days')->format(DATE_ATOM));
            $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
            $ranges[] = QueryRange::fromStrings($today->format(DATE_ATOM), $today->modify('+90 days')->format(DATE_ATOM));
            foreach (array_keys($candidate->exceptions()) as $key) {
                $occurrence = $this->expander->occurrence($candidate, $key);
                if ($occurrence !== null) {
                    $ranges = array_merge($ranges, $this->splitRange($occurrence->start, $occurrence->end));
                }
            }
        }
        return $ranges;
    }

    public function coverage(CalendarEvent $candidate, ?string $onlyKey = null): array
    {
        $ranges = $this->ranges($candidate, $onlyKey);
        $recurrence = $candidate->definition()['recurrence'];
        $complete = $onlyKey !== null || $recurrence === null;
        if ($recurrence !== null && $recurrence['until'] !== null && isset($ranges[0])) {
            $lastDay = EventSchedule::date($recurrence['until'])->modify('+'.(EventSchedule::fromArray($candidate->definition()['schedule'])->durationDays() + 1).' days');
            $complete = $lastDay <= $ranges[0]->to;
        }
        return ['ranges' => array_map(static fn (QueryRange $range): array => ['from' => $range->from->format(DATE_ATOM), 'to' => $range->to->format(DATE_ATOM)], $ranges), 'complete' => $complete];
    }

    public function conflicts(CalendarEvent $candidate, ?string $onlyKey = null): array
    {
        $conflicts = [];
        foreach ($this->ranges($candidate, $onlyKey) as $range) {
            $external = $this->calendar->busy($candidate->allParticipantIds(), $range, $candidate->id()->value());
            $occurrences = $this->expander->expand($candidate, $range->from, $range->to);
            foreach ($occurrences as $index => $occurrence) {
                if (!$occurrence->definition['blocksTime'] || ($onlyKey !== null && $occurrence->key !== $onlyKey)) {
                    continue;
                }
                $people = $this->access->activeParticipants($occurrence);
                foreach ($external as $busy) {
                    $busyStart = new \DateTimeImmutable($busy['start']);
                    $busyEnd = new \DateTimeImmutable($busy['end']);
                    if (in_array($busy['personId'], $people, true) && $busyStart < $occurrence->end && $busyEnd > $occurrence->start) {
                        $conflicts[] = ['personId' => $busy['personId'], 'start' => max($busyStart, $occurrence->start)->format(DATE_ATOM), 'end' => min($busyEnd, $occurrence->end)->format(DATE_ATOM)];
                    }
                }
                foreach ($occurrences as $otherIndex => $other) {
                    if ($otherIndex === $index || !$other->definition['blocksTime'] || $other->start >= $occurrence->end || $other->end <= $occurrence->start) {
                        continue;
                    }
                    foreach (array_intersect($people, $this->access->activeParticipants($other)) as $personId) {
                        $conflicts[] = ['personId' => $personId, 'start' => max($other->start, $occurrence->start)->format(DATE_ATOM), 'end' => min($other->end, $occurrence->end)->format(DATE_ATOM)];
                    }
                }
            }
        }
        return array_map(static fn (array $busy): array => array_diff_key($busy, ['kind' => true]), $this->intervals->merge($conflicts));
    }

    private function splitRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $ranges = [];
        while ($start < $end) {
            $next = min($end, $start->modify('+90 days'));
            $ranges[] = QueryRange::fromStrings($start->format(DATE_ATOM), $next->format(DATE_ATOM));
            $start = $next;
        }
        return $ranges;
    }
}
