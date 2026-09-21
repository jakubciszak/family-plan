<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\Service;

use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Exception\PlanningException;
use App\DayPlanning\Domain\ValueObject\EventOccurrence;
use App\DayPlanning\Domain\ValueObject\EventSchedule;
use App\DayPlanning\Domain\ValueObject\RecurrenceRule;

final class OccurrenceExpander
{
    public function expand(CalendarEvent $event, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($event->cancelled()) {
            return [];
        }
        $definition = $event->definition();
        $schedule = EventSchedule::fromArray($definition['schedule']);
        $keys = [];
        if ($definition['recurrence'] === null) {
            $keys[] = 'single';
        } else {
            $zone = new \DateTimeZone($schedule->timeZone());
            $localFrom = $from->setTimezone($zone)->modify('-'.($schedule->durationDays() + 1).' days')->format('Y-m-d');
            $localTo = $to->setTimezone($zone)->modify('+1 day')->format('Y-m-d');
            $keys = $this->keys($schedule, $definition['recurrence'], $localFrom, $localTo);
        }
        $keys = array_unique(array_merge($keys, array_keys($event->exceptions())));
        $result = [];
        foreach ($keys as $key) {
            $occurrence = $this->build($event, $key);
            if ($occurrence !== null && $occurrence->start < $to && $occurrence->end > $from) {
                $result[] = $occurrence;
            }
        }
        usort($result, static fn (EventOccurrence $left, EventOccurrence $right): int => [$left->start, $left->key] <=> [$right->start, $right->key]);
        return $result;
    }

    public function occurrence(CalendarEvent $event, string $key, bool $includeCancelled = false): ?EventOccurrence
    {
        if (!$this->validKey($event, $key)) {
            return null;
        }
        return $this->build($event, $key, $includeCancelled);
    }

    public function validKey(CalendarEvent $event, string $key): bool
    {
        $definition = $event->definition();
        if ($definition['recurrence'] === null) {
            return $key === 'single';
        }
        $schedule = EventSchedule::fromArray($definition['schedule']);
        $date = substr($key, 0, 10);
        try {
            EventSchedule::date($date);
            return $key === $schedule->keyForDate($date) && in_array($key, $this->keys($schedule, $definition['recurrence'], $date, $date), true);
        } catch (PlanningException) {
            return false;
        }
    }

    private function keys(EventSchedule $schedule, array $data, string $from, string $to): array
    {
        $rule = RecurrenceRule::fromArray($data, $schedule);
        $anchor = EventSchedule::date($schedule->anchorDate());
        $end = new \DateTimeImmutable($to, new \DateTimeZone('UTC'));
        $start = $rule->count() !== null ? $anchor : max($anchor, new \DateTimeImmutable($from, new \DateTimeZone('UTC')));
        $count = 0;
        $keys = [];
        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            if (!$rule->matches($date, $anchor)) {
                continue;
            }
            try {
                $schedule->atDate($date->format('Y-m-d'));
            } catch (PlanningException) {
                continue;
            }
            ++$count;
            if ($rule->count() !== null && $count > $rule->count()) {
                break;
            }
            if ($date->format('Y-m-d') >= $from) {
                $keys[] = $schedule->keyForDate($date->format('Y-m-d'));
            }
            if (count($keys) > 5000) {
                throw new PlanningException('range_too_complex', 422, [], 'Please narrow the calendar range.');
            }
        }
        return $keys;
    }

    private function build(CalendarEvent $event, string $key, bool $includeCancelled = false): ?EventOccurrence
    {
        $exception = $event->exceptions()[$key] ?? [];
        if (($event->cancelled() || ($exception['cancelled'] ?? false)) && !$includeCancelled) {
            return null;
        }
        $definition = array_replace($event->definition(), $exception['changes'] ?? []);
        $schedule = EventSchedule::fromArray($definition['schedule']);
        $date = isset($exception['changes']['schedule']) || $key === 'single' ? $schedule->anchorDate() : substr($key, 0, 10);
        try {
            [$start, $end] = $schedule->atDate($date);
        } catch (PlanningException) {
            return null;
        }
        return new EventOccurrence($event, $key, $start, $end, $definition, $event->participantsFor($key));
    }
}
