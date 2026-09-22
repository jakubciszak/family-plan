<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'school_timetable_imported_lessons')]
#[ORM\Index(name: 'idx_school_timetable_window', columns: ['account_id', 'occurs_on'])]
class ImportedLesson
{
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        #[ORM\Column(type: 'uuid')]
        private Uuid $accountId,
        #[ORM\Column(type: 'uuid')]
        private Uuid $eventId,
        #[ORM\Column(type: 'string', length: 64)]
        private string $studentId,
        #[ORM\Column(type: 'string', length: 10)]
        private string $occursOn,
        #[ORM\Column(type: 'string', length: 5)]
        private string $startsAt,
        #[ORM\Column(type: 'string', length: 64)]
        private string $signature,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function eventId(): Uuid
    {
        return $this->eventId;
    }

    public function studentId(): string
    {
        return $this->studentId;
    }

    public function occursOn(): string
    {
        return $this->occursOn;
    }

    public function startsAt(): string
    {
        return $this->startsAt;
    }

    public function signature(): string
    {
        return $this->signature;
    }

    public function slot(): string
    {
        return self::slotOf($this->studentId, $this->occursOn, $this->startsAt);
    }

    public static function slotOf(string $studentId, string $occursOn, string $startsAt): string
    {
        return $studentId.'|'.$occursOn.'|'.$startsAt;
    }

    public function restamp(string $signature): void
    {
        $this->signature = $signature;
    }
}
