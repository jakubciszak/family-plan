<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Application\Service;

use App\DayPlanning\Application\Service\DayPlanningService;
use App\DayPlanning\Domain\Exception\PlanningException;
use App\SchoolTimetable\Domain\Entity\ImportedLesson;
use App\SchoolTimetable\Domain\Exception\TimetableException;
use App\SchoolTimetable\Domain\Repository\ImportedLessonRepositoryInterface;
use App\SchoolTimetable\Domain\Repository\SchoolAccountRepositoryInterface;
use App\SchoolTimetable\Domain\Service\TimetableProviderInterface;
use App\SchoolTimetable\Domain\ValueObject\TimetableLesson;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class TimetableImport
{
    public function __construct(
        private SchoolAccountRepositoryInterface $accounts,
        private ImportedLessonRepositoryInterface $imported,
        private TimetableAccess $access,
        private SchoolAccounts $configuration,
        private TimetableProviderInterface $provider,
        private DayPlanningService $planning,
        private string $timeZone = 'Europe/Warsaw',
    ) {
    }

    public function run(Uuid $caller, mixed $teamId, mixed $weekStart): array
    {
        $team = $this->access->team($caller, $teamId);
        $account = $this->accounts->ofTeam($team) ?? throw TimetableException::notConfigured();

        $week = ImportWeek::from($weekStart);
        $fetched = $this->provider->week($this->configuration->credentials($account), $week);

        $account->rememberStudents($fetched->students);
        $this->accounts->save($account);

        $held = [];
        foreach ($this->imported->between($account->id(), $week, ImportWeek::end($week)) as $lesson) {
            $held[$lesson->slot()][] = $lesson;
        }

        $counts = ['added' => 0, 'updated' => 0, 'unchanged' => 0, 'removed' => 0, 'substitutions' => 0];
        $skipped = ['unlinked' => 0, 'cancelled' => 0];

        foreach ($this->onePerSlot($fetched->lessons) as $lesson) {
            if ($lesson->cancelled()) {
                ++$skipped['cancelled'];
                continue;
            }

            $userId = $account->userFor($lesson->studentId);
            if ($userId === null || !$this->access->member(Uuid::fromString($userId), $team)) {
                ++$skipped['unlinked'];
                continue;
            }

            if ($lesson->type === TimetableLesson::TYPE_SUBSTITUTION) {
                ++$counts['substitutions'];
            }

            $slot = ImportedLesson::slotOf($lesson->studentId, $lesson->date, $lesson->startTime);
            $known = isset($held[$slot]) ? array_shift($held[$slot]) : null;
            if (isset($held[$slot]) && $held[$slot] === []) {
                unset($held[$slot]);
            }

            if ($known === null) {
                $event = $this->write($caller, $team, Uuid::fromString($userId), $lesson, $account->tagId());
                $this->imported->save(new ImportedLesson(Uuid::generate(), $account->id(), Uuid::fromString($event['id']), $lesson->studentId, $lesson->date, $lesson->startTime, $lesson->signature()));
                ++$counts['added'];
                continue;
            }

            if ($known->signature() === $lesson->signature()) {
                ++$counts['unchanged'];
                continue;
            }

            if (!$this->revise($caller, $team, Uuid::fromString($userId), $known, $lesson, $account->tagId())) {
                $this->imported->remove($known);
                $event = $this->write($caller, $team, Uuid::fromString($userId), $lesson, $account->tagId());
                $this->imported->save(new ImportedLesson(Uuid::generate(), $account->id(), Uuid::fromString($event['id']), $lesson->studentId, $lesson->date, $lesson->startTime, $lesson->signature()));
                ++$counts['added'];
                continue;
            }

            $known->restamp($lesson->signature());
            $this->imported->save($known);
            ++$counts['updated'];
        }

        foreach ($held as $slot) {
            foreach ($slot as $gone) {
                $this->withdraw($caller, $gone);
                ++$counts['removed'];
            }
        }

        $account->noteImport(new \DateTimeImmutable());
        $this->accounts->save($account);

        return ['weekStart' => $week, 'weekEnd' => ImportWeek::end($week), ...$counts, 'skipped' => $skipped, 'account' => $account->describe()];
    }

    /**
     * @param TimetableLesson[] $lessons
     *
     * @return TimetableLesson[]
     */
    private function onePerSlot(array $lessons): array
    {
        $kept = [];
        foreach ($lessons as $lesson) {
            $slot = ImportedLesson::slotOf($lesson->studentId, $lesson->date, $lesson->startTime);
            if (!array_key_exists($slot, $kept)) {
                $kept[$slot] = $lesson;
            }
        }

        return array_values($kept);
    }

    private function withdraw(Uuid $caller, ImportedLesson $lesson): void
    {
        try {
            $definition = $this->planning->definition($caller, $lesson->eventId());
            $this->planning->cancel($caller, $lesson->eventId(), $definition['version']);
        } catch (PlanningException $gone) {
            if ($gone->status !== 404) {
                throw $gone;
            }
        }

        $this->imported->remove($lesson);
    }

    private function revise(Uuid $caller, Uuid $team, Uuid $student, ImportedLesson $known, TimetableLesson $lesson, ?string $tagId): bool
    {
        try {
            $definition = $this->planning->definition($caller, $known->eventId());
        } catch (PlanningException $gone) {
            if ($gone->status === 404) {
                return false;
            }

            throw $gone;
        }

        if (($definition['cancelled'] ?? false) === true) {
            return false;
        }

        $payload = $this->payload($team, $student, $lesson, $tagId) + ['resetExceptions' => true];

        try {
            $this->planning->update($caller, $known->eventId(), $definition['version'], $payload);
        } catch (PlanningException $conflict) {
            $token = $conflict->details['confirmationToken'] ?? null;
            if ($conflict->status !== 409 || !is_string($token)) {
                throw $conflict;
            }

            $this->planning->update($caller, $known->eventId(), $definition['version'], $payload + ['conflictConfirmation' => $token]);
        }

        return true;
    }

    private function write(Uuid $caller, Uuid $team, Uuid $student, TimetableLesson $lesson, ?string $tagId): array
    {
        $payload = $this->payload($team, $student, $lesson, $tagId);

        try {
            return $this->planning->create($caller, $payload, null);
        } catch (PlanningException $conflict) {
            $token = $conflict->details['confirmationToken'] ?? null;
            if ($conflict->status !== 409 || !is_string($token)) {
                throw $conflict;
            }

            return $this->planning->create($caller, $payload + ['conflictConfirmation' => $token], null);
        }
    }

    private function payload(Uuid $team, Uuid $student, TimetableLesson $lesson, ?string $tagId): array
    {
        return [
            'title' => $lesson->title(),
            'description' => $this->description($lesson),
            'location' => $lesson->classroom ?? '',
            'teamId' => $team->value(),
            'visibility' => 'PRIVATE',
            'blocksTime' => true,
            'ownerParticipates' => false,
            'participantIds' => [$student->value()],
            'tagIds' => $tagId === null ? [] : [$tagId],
            'schedule' => [
                'kind' => 'TIMED',
                'localStart' => $lesson->localStart(),
                'durationMinutes' => $lesson->durationMinutes(),
                'timeZone' => $this->timeZone,
            ],
        ];
    }

    private function description(TimetableLesson $lesson): string
    {
        $lines = array_filter([
            $lesson->type === TimetableLesson::TYPE_SUBSTITUTION ? 'zastępstwo' : null,
            $lesson->teacher,
            $lesson->group,
            $lesson->isExtra ? 'zajęcia pozalekcyjne' : null,
        ]);

        return implode("\n", $lines);
    }
}
