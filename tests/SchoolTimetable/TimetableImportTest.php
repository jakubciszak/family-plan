<?php

declare(strict_types=1);

namespace App\Tests\SchoolTimetable;

use App\DayPlanning\Application\Query\CalendarView;
use App\DayPlanning\Application\Service\ConflictConfirmation;
use App\DayPlanning\Application\Service\ConflictDetector;
use App\DayPlanning\Application\Service\DayPlanningService;
use App\DayPlanning\Application\Service\PlanningAccess;
use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Entity\CalendarTag;
use App\DayPlanning\Domain\Repository\CalendarEventRepositoryInterface;
use App\DayPlanning\Domain\Repository\CalendarTagRepositoryInterface;
use App\DayPlanning\Domain\Service\BusyIntervals;
use App\DayPlanning\Domain\Service\OccurrenceExpander;
use App\SchoolTimetable\Application\Service\SchoolAccounts;
use App\SchoolTimetable\Application\Service\TimetableAccess;
use App\SchoolTimetable\Application\Service\TimetableImport;
use App\SchoolTimetable\Domain\Entity\ImportedLesson;
use App\SchoolTimetable\Domain\Entity\SchoolAccount;
use App\SchoolTimetable\Domain\Exception\TimetableException;
use App\SchoolTimetable\Domain\Repository\ImportedLessonRepositoryInterface;
use App\SchoolTimetable\Domain\Repository\SchoolAccountRepositoryInterface;
use App\SchoolTimetable\Domain\Service\SecretCipherInterface;
use App\SchoolTimetable\Domain\Service\TimetableProviderInterface;
use App\SchoolTimetable\Domain\ValueObject\SchoolCredentials;
use App\SchoolTimetable\Domain\ValueObject\TimetableLesson;
use App\SchoolTimetable\Domain\ValueObject\TimetableWeek;
use App\SchoolTimetable\Infrastructure\Mobidziennik\TimetableParser;
use App\SchoolTimetable\Infrastructure\Security\SodiumSecretCipher;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TimetableImportTest extends TestCase
{
    private Uuid $parent;
    private Uuid $team;
    private Uuid $ola;
    private CalendarEventRepositoryInterface $events;
    private ImportedLessonRepositoryInterface $imported;
    private SchoolAccountRepositoryInterface $accounts;
    private SchoolAccount $account;
    private array $lessons = [];
    private ?CalendarTag $tag = null;
    private array $students = [];

    public function testALinkedStudentGetsTheirLessonsInTheirOwnCalendar(): void
    {
        $summary = $this->import()->run($this->parent, $this->team->value(), '2026-09-21');

        self::assertSame(['weekStart' => '2026-09-21', 'weekEnd' => '2026-09-27'], array_intersect_key($summary, ['weekStart' => null, 'weekEnd' => null]));
        self::assertSame(2, $summary['added']);

        $written = $this->events->forPeople([$this->ola->value()]);
        self::assertCount(2, $written);

        $first = $written[0]->describe();
        self::assertSame('Matematyka', $first['title']);
        self::assertSame('Sala 12', $first['location']);
        self::assertStringContainsString('Kowalska Maria', $first['description']);
        self::assertSame([$this->ola->value()], $first['participantIds']);
        self::assertFalse($first['ownerParticipates']);
        self::assertSame($this->parent->value(), $first['ownerId']);
        self::assertSame(['kind' => 'TIMED', 'localStart' => '2026-09-21T08:00', 'durationMinutes' => 45, 'timeZone' => 'Europe/Warsaw'], $first['schedule']);
    }

    public function testACancelledLessonAndAnUnlinkedStudentAreLeftOut(): void
    {
        $summary = $this->import()->run($this->parent, $this->team->value(), '2026-09-21');

        self::assertSame(['unlinked' => 1, 'cancelled' => 1], $summary['skipped']);
    }

    public function testASubstitutionSaysSoInItsDescription(): void
    {
        $this->import()->run($this->parent, $this->team->value(), '2026-09-21');

        self::assertStringContainsString('zastępstwo', $this->events->forPeople([$this->ola->value()])[1]->describe()['description']);
    }

    public function testAnUnchangedWeekImportedAgainTouchesNothing(): void
    {
        $import = $this->import();
        $import->run($this->parent, $this->team->value(), '2026-09-21');
        $before = array_map(static fn ($event) => $event->describe()['id'], $this->events->forPeople([$this->ola->value()]));

        $summary = $import->run($this->parent, $this->team->value(), '2026-09-21');

        self::assertSame(['added' => 0, 'updated' => 0, 'unchanged' => 2, 'removed' => 0], array_intersect_key($summary, ['added' => null, 'updated' => null, 'unchanged' => null, 'removed' => null]));
        self::assertSame($before, array_map(static fn ($event) => $event->describe()['id'], $this->events->forPeople([$this->ola->value()])));
        self::assertCount(2, $this->imported->between($this->account->id(), '2026-09-21', '2026-09-27'));
    }

    public function testATeacherSwappedInTheSameSlotUpdatesTheSameEvent(): void
    {
        $import = $this->import();
        $import->run($this->parent, $this->team->value(), '2026-09-21');
        $before = $this->events->forPeople([$this->ola->value()])[0]->describe();

        $this->lessons[0] = $this->withTeacher($this->lessons[0], 'Zastępcza Zofia');
        $summary = $import->run($this->parent, $this->team->value(), '2026-09-21');

        $after = $this->events->forPeople([$this->ola->value()])[0]->describe();
        self::assertSame(1, $summary['updated']);
        self::assertSame(0, $summary['added']);
        self::assertSame($before['id'], $after['id']);
        self::assertStringContainsString('Zastępcza Zofia', $after['description']);
    }

    public function testALessonGoneFromThePlanIsCancelled(): void
    {
        $import = $this->import();
        $import->run($this->parent, $this->team->value(), '2026-09-21');
        $eventId = $this->events->forPeople([$this->ola->value()])[0]->describe()['id'];

        array_shift($this->lessons);
        $summary = $import->run($this->parent, $this->team->value(), '2026-09-21');

        self::assertSame(1, $summary['removed']);
        self::assertTrue($this->events->find(Uuid::fromString($eventId))->cancelled());
        self::assertCount(1, $this->imported->between($this->account->id(), '2026-09-21', '2026-09-27'));
    }

    public function testALessonListedInBothPlansLandsOnce(): void
    {
        $this->lessons[] = $this->lessons[0];

        $summary = $this->import()->run($this->parent, $this->team->value(), '2026-09-21');

        self::assertSame(2, $summary['added']);
        self::assertCount(2, $this->events->forPeople([$this->ola->value()]));
    }

    public function testEarlierDuplicatesOfASlotAreWithdrawn(): void
    {
        $import = $this->import();
        $this->lessons[] = $this->lessons[0];
        $import->run($this->parent, $this->team->value(), '2026-09-21');

        $summary = $import->run($this->parent, $this->team->value(), '2026-09-21');

        self::assertSame(0, $summary['added']);
        self::assertSame(2, $summary['unchanged']);
        self::assertSame(0, $summary['removed']);
    }

    public function testASubstitutionIsCountedInTheSummary(): void
    {
        self::assertSame(1, $this->import()->run($this->parent, $this->team->value(), '2026-09-21')['substitutions']);
    }

    public function testEventsCarryTheTagChosenForTheAccount(): void
    {
        $this->tag = CalendarTag::create(Uuid::generate(), $this->parent, ['name' => 'Szkoła', 'scope' => 'TEAM', 'teamId' => $this->team->value()]);
        $this->account->useTag($this->tag->id()->value());

        $this->import()->run($this->parent, $this->team->value(), '2026-09-21');

        self::assertSame([$this->tag->id()->value()], $this->events->forPeople([$this->ola->value()])[0]->describe()['tagIds']);
    }

    public function tagOrNull(Uuid $id): ?CalendarTag
    {
        return $this->tag !== null && $this->tag->id()->equals($id) ? $this->tag : null;
    }

    public function testWithoutATagTheEventsCarryNone(): void
    {
        $this->import()->run($this->parent, $this->team->value(), '2026-09-21');

        self::assertSame([], $this->events->forPeople([$this->ola->value()])[0]->describe()['tagIds']);
    }

    private function withTeacher(TimetableLesson $lesson, string $teacher): TimetableLesson
    {
        return new TimetableLesson($lesson->studentId, $lesson->studentName, $lesson->date, $lesson->startTime, $lesson->endTime, $lesson->subject, $teacher, $lesson->classroom, $lesson->group, $lesson->type, $lesson->isExtra);
    }

    public function testSomebodyWhoDoesNotRunTheTeamCannotImport(): void
    {
        $this->expectException(TimetableException::class);
        $this->import()->run(Uuid::generate(), $this->team->value(), '2026-09-21');
    }

    protected function setUp(): void
    {
        $this->parent = Uuid::generate();
        $this->team = Uuid::generate();
        $this->ola = Uuid::generate();

        $cipher = new SodiumSecretCipher('test-secret');
        $this->account = SchoolAccount::create(Uuid::generate(), $this->team, 'szkola', 'rodzic@example.com', $cipher->encrypt('hasło'));
        $this->account->rememberStudents([['id' => '7', 'name' => 'Nowak Ola'], ['id' => '11', 'name' => 'Nowak Jaś']]);
        $this->account->linkStudents(['7' => $this->ola->value()]);

        $html = (string) file_get_contents(__DIR__.'/fixtures/planlekcji.html');
        $this->students = (new TimetableParser())->students($html);
        $this->lessons = (new TimetableParser())->lessons($html, false);

        $this->events = $this->eventRepository();
        $this->imported = $this->importedLessonRepository();
        $this->accounts = $this->accountRepository($this->account);
    }

    private function import(): TimetableImport
    {
        $memberships = $this->memberships();
        $access = new TimetableAccess($memberships);
        $cipher = new SodiumSecretCipher('test-secret');
        $provider = $this->provider();

        return new TimetableImport($this->accounts, $this->imported, $access, new SchoolAccounts($this->accounts, $access, $cipher, $provider), $provider, $this->planning($memberships), 'Europe/Warsaw');
    }

    private function planning(TeamMembershipRepositoryInterface $memberships): DayPlanningService
    {
        $access = new PlanningAccess($memberships);
        $expander = new OccurrenceExpander();
        $intervals = new BusyIntervals();
        $tags = new class($this) implements CalendarTagRepositoryInterface {
            public function __construct(private readonly TimetableImportTest $test)
            {
            }

            public function find(Uuid $id): ?CalendarTag
            {
                return $this->test->tagOrNull($id);
            }

            public function visibleTo(Uuid $ownerId, ?Uuid $teamId): array
            {
                return [];
            }

            public function save(CalendarTag $tag): void
            {
            }
        };
        $view = new CalendarView($this->events, $tags, $access, $expander, $intervals);
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event, ?string $eventName = null): object
            {
                return $event;
            }
        };

        return new DayPlanningService($this->events, $tags, $access, $expander, new ConflictDetector($view, $expander, $access, $intervals), new ConflictConfirmation('test-secret'), $dispatcher);
    }

    private function provider(): TimetableProviderInterface
    {
        return new class($this) implements TimetableProviderInterface {
            public function __construct(private readonly TimetableImportTest $test)
            {
            }

            public function week(SchoolCredentials $credentials, string $weekStart): TimetableWeek
            {
                return $this->test->plan();
            }
        };
    }

    public function plan(): TimetableWeek
    {
        return new TimetableWeek($this->students, array_values($this->lessons));
    }

    private function memberships(): TeamMembershipRepositoryInterface
    {
        return new class($this->parent, $this->team, $this->ola) implements TeamMembershipRepositoryInterface {
            public function __construct(private readonly Uuid $admin, private readonly Uuid $team, private readonly Uuid $child)
            {
            }

            public function join(Uuid $teamId, Uuid $userId, TeamRole $role): ?TeamMembership
            {
                return null;
            }

            public function leave(Uuid $teamId, Uuid $userId): void
            {
            }

            public function find(Uuid $teamId, Uuid $userId): ?TeamMembership
            {
                return null;
            }

            public function ofTeam(Uuid $teamId): array
            {
                return [];
            }

            public function ofUser(Uuid $userId): array
            {
                return [];
            }

            public function isMember(Uuid $userId, Uuid $teamId): bool
            {
                return $teamId->equals($this->team) && ($userId->equals($this->admin) || $userId->equals($this->child));
            }

            public function isAdmin(Uuid $userId, Uuid $teamId): bool
            {
                return $teamId->equals($this->team) && $userId->equals($this->admin);
            }
        };
    }

    private function eventRepository(): CalendarEventRepositoryInterface
    {
        return new class implements CalendarEventRepositoryInterface {
            private array $events = [];

            public function find(Uuid $id): ?CalendarEvent
            {
                return $this->events[$id->value()] ?? null;
            }

            public function forPeople(array $personIds): array
            {
                return array_values(array_filter($this->events, static fn (CalendarEvent $event): bool => !$event->cancelled() && array_intersect($personIds, $event->allParticipantIds()) !== []));
            }

            public function findByTeam(string $teamId): array
            {
                return array_values($this->events);
            }

            public function save(CalendarEvent $event): void
            {
                $this->events[$event->id()->value()] = $event;
            }

            public function transactional(callable $operation): mixed
            {
                return $operation();
            }

            public function idempotentResult(string $ownerId, string $key, string $hash): ?array
            {
                return null;
            }

            public function rememberResult(string $ownerId, string $key, string $hash, array $result): void
            {
            }
        };
    }

    private function importedLessonRepository(): ImportedLessonRepositoryInterface
    {
        return new class implements ImportedLessonRepositoryInterface {
            private array $lessons = [];

            public function between(Uuid $accountId, string $from, string $to): array
            {
                return array_values(array_filter($this->lessons, static fn (ImportedLesson $lesson): bool => $lesson->occursOn() >= $from && $lesson->occursOn() <= $to));
            }

            public function save(ImportedLesson $lesson): void
            {
                $this->lessons[$lesson->id()->value()] = $lesson;
            }

            public function remove(ImportedLesson $lesson): void
            {
                unset($this->lessons[$lesson->id()->value()]);
            }
        };
    }

    private function accountRepository(SchoolAccount $account): SchoolAccountRepositoryInterface
    {
        return new class($account) implements SchoolAccountRepositoryInterface {
            public function __construct(private ?SchoolAccount $account)
            {
            }

            public function ofTeam(Uuid $teamId): ?SchoolAccount
            {
                return $this->account;
            }

            public function all(): array
            {
                return $this->account === null ? [] : [$this->account];
            }

            public function save(SchoolAccount $account): void
            {
                $this->account = $account;
            }

            public function remove(SchoolAccount $account): void
            {
                $this->account = null;
            }
        };
    }
}
