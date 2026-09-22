<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Domain\ValueObject;

final readonly class TimetableLesson
{
    public const TYPE_NORMAL = 'normal';
    public const TYPE_SUBSTITUTION = 'substitution';
    public const TYPE_CANCELLED = 'cancelled';

    public function __construct(
        public string $studentId,
        public string $studentName,
        public string $date,
        public string $startTime,
        public string $endTime,
        public ?string $subject,
        public ?string $teacher,
        public ?string $classroom,
        public ?string $group,
        public string $type,
        public bool $isExtra,
    ) {
    }

    public function cancelled(): bool
    {
        return $this->type === self::TYPE_CANCELLED;
    }

    public function durationMinutes(): int
    {
        $start = $this->minutes($this->startTime);
        $end = $this->minutes($this->endTime);

        return $end > $start ? $end - $start : 45;
    }

    public function localStart(): string
    {
        return $this->date.'T'.$this->startTime;
    }

    public function title(): string
    {
        return $this->subject ?? 'Lekcja';
    }

    public function signature(): string
    {
        return hash('sha256', implode('|', [$this->studentId, $this->date, $this->startTime, $this->endTime, $this->subject ?? '', $this->teacher ?? '', $this->classroom ?? '', $this->group ?? '', $this->type]));
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_pad(array_map(intval(...), explode(':', $time)), 2, 0);

        return $hours * 60 + $minutes;
    }
}
