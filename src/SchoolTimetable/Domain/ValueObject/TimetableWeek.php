<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Domain\ValueObject;

final readonly class TimetableWeek
{
    /**
     * @param array<int, array{id: string, name: string}> $students
     * @param TimetableLesson[]                           $lessons
     */
    public function __construct(
        public array $students,
        public array $lessons,
    ) {
    }
}
