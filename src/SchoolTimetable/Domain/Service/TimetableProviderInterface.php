<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Domain\Service;

use App\SchoolTimetable\Domain\ValueObject\SchoolCredentials;
use App\SchoolTimetable\Domain\ValueObject\TimetableWeek;

interface TimetableProviderInterface
{
    public function week(SchoolCredentials $credentials, string $weekStart): TimetableWeek;
}
