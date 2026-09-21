<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\ValueObject;

use App\DayPlanning\Domain\Exception\PlanningException;

final readonly class RecurrenceRule
{
    private function __construct(private array $data)
    {
    }

    public static function fromArray(array $data, EventSchedule $schedule): self
    {
        $frequency = $data['frequency'] ?? null;
        $interval = $data['interval'] ?? 1;
        $byDay = $data['byDay'] ?? [];
        $until = $data['until'] ?? null;
        $count = $data['count'] ?? null;
        if (!in_array($frequency, ['DAILY', 'WEEKLY'], true) || !is_int($interval) || $interval < 1 || $interval > 52 || !is_array($byDay) || !array_is_list($byDay) || count($byDay) > 7) {
            throw PlanningException::invalid('Invalid daily or weekly recurrence.');
        }
        foreach ($byDay as $day) {
            if (!is_int($day) || $day < 1 || $day > 7) {
                throw PlanningException::invalid('Weekdays must be numbers from 1 to 7.');
            }
        }
        if ($frequency === 'WEEKLY' && $byDay === []) {
            $byDay = [(int) EventSchedule::date($schedule->anchorDate())->format('N')];
        }
        $byDay = array_values(array_unique($byDay));
        sort($byDay);
        if ($until !== null && (!is_string($until) || EventSchedule::date($until)->format('Y-m-d') < $schedule->anchorDate())) {
            throw PlanningException::invalid('Recurrence end must not precede its start.');
        }
        if ($count !== null && (!is_int($count) || $count < 1 || $count > 10000)) {
            throw PlanningException::invalid('Recurrence count must be between 1 and 10000.');
        }
        if ($count !== null && $until !== null) {
            throw PlanningException::invalid('Use either a recurrence end date or count.');
        }
        return new self(['frequency' => $frequency, 'interval' => $interval, 'byDay' => $byDay, 'until' => $until, 'count' => $count]);
    }

    public function describe(): array
    {
        return $this->data;
    }

    public function matches(\DateTimeImmutable $date, \DateTimeImmutable $anchor): bool
    {
        if ($date < $anchor || ($this->data['until'] !== null && $date->format('Y-m-d') > $this->data['until'])) {
            return false;
        }
        $days = (int) $anchor->diff($date)->days;
        if ($this->data['frequency'] === 'DAILY') {
            return $days % $this->data['interval'] === 0 && ($this->data['byDay'] === [] || in_array((int) $date->format('N'), $this->data['byDay'], true));
        }
        $weeks = intdiv($days + (int) $anchor->format('N') - 1, 7);
        return $weeks % $this->data['interval'] === 0 && in_array((int) $date->format('N'), $this->data['byDay'], true);
    }

    public function count(): ?int
    {
        return $this->data['count'];
    }
}
