<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Domain\Entity;

use App\SchoolTimetable\Domain\ValueObject\StudentLink;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'school_timetable_accounts')]
#[ORM\UniqueConstraint(name: 'uniq_school_timetable_team', columns: ['team_id'])]
class SchoolAccount
{
    #[ORM\Column(type: 'json')]
    private array $students = [];

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $tagId = null;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $ownerId = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $importedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,
        #[ORM\Column(type: 'uuid')]
        private Uuid $teamId,
        #[ORM\Column(type: 'string', length: 64)]
        private string $schoolId,
        #[ORM\Column(type: 'string', length: 190)]
        private string $login,
        #[ORM\Column(type: 'text')]
        private string $secret,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function create(Uuid $id, Uuid $teamId, string $schoolId, string $login, string $secret): self
    {
        return new self($id, $teamId, $schoolId, $login, $secret);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function teamId(): Uuid
    {
        return $this->teamId;
    }

    public function schoolId(): string
    {
        return $this->schoolId;
    }

    public function login(): string
    {
        return $this->login;
    }

    public function secret(): string
    {
        return $this->secret;
    }

    public function changeSignIn(string $schoolId, string $login, ?string $secret): void
    {
        $this->schoolId = $schoolId;
        $this->login = $login;
        if ($secret !== null) {
            $this->secret = $secret;
        }
        $this->touch();
    }

    /**
     * @return StudentLink[]
     */
    public function students(): array
    {
        return array_map(StudentLink::fromArray(...), $this->students);
    }

    public function userFor(string $studentId): ?string
    {
        foreach ($this->students() as $student) {
            if ($student->studentId === $studentId) {
                return $student->userId;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{id: string, name: string}> $found
     */
    public function rememberStudents(array $found): void
    {
        $known = [];
        foreach ($this->students() as $student) {
            $known[$student->studentId] = $student;
        }

        $students = [];
        foreach ($found as $student) {
            $link = $known[$student['id']] ?? new StudentLink($student['id'], $student['name']);
            $students[] = $link->withName($student['name'])->describe();
        }

        $this->students = $students;
        $this->touch();
    }

    /**
     * @param array<string, string|null> $links
     */
    public function linkStudents(array $links): void
    {
        $students = [];
        foreach ($this->students() as $student) {
            $students[] = array_key_exists($student->studentId, $links)
                ? $student->withUser($links[$student->studentId])->describe()
                : $student->describe();
        }

        $this->students = $students;
        $this->touch();
    }

    public function ownerId(): ?string
    {
        return $this->ownerId;
    }

    public function configuredBy(string $ownerId): void
    {
        $this->ownerId = $ownerId;
        $this->touch();
    }

    public function linkedStudents(): array
    {
        return array_values(array_filter($this->students(), static fn (StudentLink $student): bool => $student->userId !== null));
    }

    public function tagId(): ?string
    {
        return $this->tagId;
    }

    public function useTag(?string $tagId): void
    {
        $this->tagId = $tagId;
        $this->touch();
    }

    public function noteImport(\DateTimeImmutable $at): void
    {
        $this->importedAt = $at;
        $this->touch();
    }

    public function describe(): array
    {
        return [
            'teamId' => $this->teamId->value(),
            'schoolId' => $this->schoolId,
            'login' => $this->login,
            'students' => array_map(static fn (StudentLink $student): array => $student->describe(), $this->students()),
            'tagId' => $this->tagId,
            'importedAt' => $this->importedAt?->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
