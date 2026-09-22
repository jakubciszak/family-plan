<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Domain\Repository;

use App\SchoolTimetable\Domain\Entity\ImportedLesson;
use App\Shared\Domain\ValueObject\Uuid;

interface ImportedLessonRepositoryInterface
{
    /**
     * @return ImportedLesson[]
     */
    public function between(Uuid $accountId, string $from, string $to): array;

    public function save(ImportedLesson $lesson): void;

    public function remove(ImportedLesson $lesson): void;
}
