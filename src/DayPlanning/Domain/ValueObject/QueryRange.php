<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\ValueObject;

use App\DayPlanning\Domain\Exception\PlanningException;

final readonly class QueryRange
{
    private function __construct(public \DateTimeImmutable $from, public \DateTimeImmutable $to)
    {
    }

    public static function fromStrings(mixed $from, mixed $to): self
    {
        if (!is_string($from) || !is_string($to)) {
            throw PlanningException::invalid('A range start and end are required.');
        }
        $start = self::instant($from);
        $end = self::instant($to);
        if ($end <= $start || $end->getTimestamp() - $start->getTimestamp() > 90 * 86400 + 7200) {
            throw PlanningException::invalid('The requested range must be positive and no longer than 90 days.');
        }
        return new self($start, $end);
    }

    private static function instant(string $value): \DateTimeImmutable
    {
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})(?::(\d{2})(?:\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/D', $value, $parts) || (int) $parts[2] > 23 || (int) $parts[3] > 59 || (isset($parts[4]) && (int) $parts[4] > 59)) {
            throw PlanningException::invalid('Use an ISO 8601 timestamp with an explicit offset.');
        }
        $offset = $parts[5];
        if ($offset !== 'Z' && ((int) substr($offset, 1, 2) > 23 || (int) substr($offset, 4, 2) > 59)) {
            throw PlanningException::invalid('Invalid timestamp offset.');
        }
        EventSchedule::date($parts[1]);
        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw PlanningException::invalid('Invalid timestamp.');
        }
    }

    public function coverage(): array
    {
        return ['from' => $this->from->format(DATE_ATOM), 'to' => $this->to->format(DATE_ATOM), 'complete' => true];
    }
}
