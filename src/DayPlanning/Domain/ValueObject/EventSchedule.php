<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\ValueObject;

use App\DayPlanning\Domain\Exception\PlanningException;

final readonly class EventSchedule
{
    private function __construct(private array $data)
    {
    }

    public static function fromArray(array $data): self
    {
        $zone = $data['timeZone'] ?? null;
        if (!is_string($zone) || !in_array($zone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            throw PlanningException::invalid('Invalid IANA time zone.');
        }
        if (($data['kind'] ?? null) === 'TIMED') {
            $local = $data['localStart'] ?? null;
            $duration = $data['durationMinutes'] ?? null;
            if (!is_string($local) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $local) || !is_int($duration) || $duration < 1 || $duration > 10080) {
                throw PlanningException::invalid('A timed event needs a local start and a duration of 1–10080 minutes.');
            }
            self::date(substr($local, 0, 10));
            $offset = $data['utcOffset'] ?? null;
            if ($offset !== null && (!is_string($offset) || !preg_match('/^[+-]\d{2}:\d{2}$/D', $offset))) {
                throw PlanningException::invalid('Invalid UTC offset.');
            }
            self::localInstant($local, $zone, $offset);
            return new self(array_filter(['kind' => 'TIMED', 'localStart' => $local, 'durationMinutes' => $duration, 'timeZone' => $zone, 'utcOffset' => $offset], static fn ($value) => $value !== null));
        }
        if (($data['kind'] ?? null) !== 'ALL_DAY' || !is_string($data['startDate'] ?? null) || !is_string($data['endDate'] ?? null)) {
            throw PlanningException::invalid('Invalid event schedule.');
        }
        $start = self::date($data['startDate']);
        $end = self::date($data['endDate']);
        if ($end <= $start || $start->diff($end)->days > 366) {
            throw PlanningException::invalid('An all-day event needs an exclusive end date within 366 days.');
        }
        return new self(['kind' => 'ALL_DAY', 'startDate' => $data['startDate'], 'endDate' => $data['endDate'], 'timeZone' => $zone]);
    }

    public static function date(string $date): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < '1900-01-01' || $date > '2200-12-31') {
            throw PlanningException::invalid('Invalid calendar date.');
        }
        return $parsed;
    }

    public static function localInstant(string $local, string $zone, ?string $preferredOffset = null): \DateTimeImmutable
    {
        $wall = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, new \DateTimeZone('UTC'));
        if (!$wall || $wall->format('Y-m-d\TH:i') !== $local) {
            throw PlanningException::invalid('Invalid local date and time.');
        }
        $timezone = new \DateTimeZone($zone);
        $transitions = $timezone->getTransitions($wall->getTimestamp() - 172800, $wall->getTimestamp() + 172800);
        $offsets = array_unique(array_column($transitions ?: [], 'offset'));
        $candidates = [];
        foreach ($offsets as $offset) {
            $instant = $wall->modify(sprintf('%+d seconds', -$offset));
            $localCandidate = $instant->setTimezone($timezone);
            if ($localCandidate->format('Y-m-d\TH:i') === $local && ($preferredOffset === null || $localCandidate->format('P') === $preferredOffset)) {
                $candidates[] = $instant;
            }
        }
        if ($candidates === []) {
            throw PlanningException::invalid('This local time does not exist in the selected time zone.');
        }
        usort($candidates, static fn ($left, $right) => $left <=> $right);
        return $candidates[0];
    }

    public function describe(): array
    {
        return $this->data;
    }

    public function allDay(): bool
    {
        return $this->data['kind'] === 'ALL_DAY';
    }

    public function timeZone(): string
    {
        return $this->data['timeZone'];
    }

    public function anchorDate(): string
    {
        return $this->allDay() ? $this->data['startDate'] : substr($this->data['localStart'], 0, 10);
    }

    public function keyForDate(string $date): string
    {
        return $this->allDay() ? $date : $date.substr($this->data['localStart'], 10);
    }

    public function durationDays(): int
    {
        return $this->allDay() ? (int) self::date($this->data['startDate'])->diff(self::date($this->data['endDate']))->days : (int) ceil($this->data['durationMinutes'] / 1440);
    }

    public function atDate(string $date): array
    {
        if ($this->allDay()) {
            $zone = new \DateTimeZone($this->timeZone());
            $start = new \DateTimeImmutable($date.'T00:00:00', $zone);
            $endDate = self::date($date)->modify('+'.$this->durationDays().' days')->format('Y-m-d');
            $end = new \DateTimeImmutable($endDate.'T00:00:00', $zone);
            return [$start->setTimezone(new \DateTimeZone('UTC')), $end->setTimezone(new \DateTimeZone('UTC'))];
        }
        $offset = $date === $this->anchorDate() ? ($this->data['utcOffset'] ?? null) : null;
        $start = self::localInstant($this->keyForDate($date), $this->timeZone(), $offset);
        return [$start, $start->modify('+'.$this->data['durationMinutes'].' minutes')];
    }
}
