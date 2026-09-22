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
use App\SchoolTimetable\Application\Service\TimetableSync;
use App\SchoolTimetable\Domain\Entity\ImportedLesson;
use App\SchoolTimetable\Domain\Entity\SchoolAccount;
use App\SchoolTimetable\Domain\Repository\ImportedLessonRepositoryInterface;
use App\SchoolTimetable\Domain\Repository\SchoolAccountRepositoryInterface;
use App\SchoolTimetable\Domain\Service\TimetableProviderInterface;
use App\SchoolTimetable\Domain\ValueObject\SchoolCredentials;
use App\SchoolTimetable\Domain\ValueObject\TimetableWeek;
use App\SchoolTimetable\Infrastructure\Security\SodiumSecretCipher;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TimetableSyncTest extends TestCase
{
    private Uuid $admin;
    private Uuid $team;
    private Uuid $child;
    private SchoolAccount $account;
    public array $asked = [];
    public ?\Throwable $failure = null;

    public function testAnAccountWithoutLinkedStudentsIsLeftAlone(): void
    {
        $report = $this->sync()->run();

        self::assertSame('no_linked_students', $report[0]['skipped']);
        self::assertSame([], $this->asked);
    }

    public function testALinkedAccountIsImportedWeekByWeek(): void
    {
        $this->account->linkStudents(['7' => $this->child->value()]);

        $report = $this->sync()->run(2);

        self::assertCount(2, $this->asked);
        self::assertSame($this->asked[1], (new \DateTimeImmutable($this->asked[0]))->modify('+7 days')->format('Y-m-d'));
        self::assertCount(2, $report[0]['weeks']);
    }

    public function testTheRunActsForTheAdminWhoConfiguredIt(): void
    {
        $this->account->linkStudents(['7' => $this->child->value()]);
        $this->account->configuredBy($this->admin->value());

        $this->sync()->run(1);

        self::assertSame($this->admin->value(), $this->account->ownerId());
    }

    public function testAnAccountWithoutAnOwnerAdoptsATeamAdmin(): void
    {
        $this->account->linkStudents(['7' => $this->child->value()]);

        $this->sync()->run(1);

        self::assertSame($this->admin->value(), $this->account->ownerId());
    }

    public function testAFailingSchoolIsReportedInsteadOfBreakingTheRun(): void
    {
        $this->account->linkStudents(['7' => $this->child->value()]);
        $this->failure = new \RuntimeException('mobidziennik down');

        $report = $this->sync()->run(2);

        self::assertSame('mobidziennik down', $report[0]['failed']);
    }

    protected function setUp(): void
    {
        $this->admin = Uuid::generate();
        $this->team = Uuid::generate();
        $this->child = Uuid::generate();

        $this->account = SchoolAccount::create(Uuid::generate(), $this->team, 'szkola', 'rodzic@example.com', (new SodiumSecretCipher('test-secret'))->encrypt('hasło'));
        $this->account->rememberStudents([['id' => '7', 'name' => 'Nowak Ola']]);
    }

    public function answer(string $weekStart): TimetableWeek
    {
        $this->asked[] = $weekStart;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new TimetableWeek([['id' => '7', 'name' => 'Nowak Ola']], []);
    }

    private function sync(): TimetableSync
    {
        $accounts = $this->accountRepository();
        $access = new TimetableAccess($this->memberships());
        $provider = $this->provider();
        $configuration = new SchoolAccounts($accounts, $access, new SodiumSecretCipher('test-secret'), $provider);
        $import = new TimetableImport($accounts, $this->importedLessons(), $access, $configuration, $provider, $this->planning(), 'Europe/Warsaw');

        return new TimetableSync($accounts, $access, $import);
    }

    private function provider(): TimetableProviderInterface
    {
        return new class($this) implements TimetableProviderInterface {
            public function __construct(private readonly TimetableSyncTest $test)
            {
            }

            public function week(SchoolCredentials $credentials, string $weekStart): TimetableWeek
            {
                return $this->test->answer($weekStart);
            }
        };
    }

    private function planning(): DayPlanningService
    {
        $events = new class implements CalendarEventRepositoryInterface {
            public function find(Uuid $id): ?CalendarEvent
            {
                return null;
            }

            public function forPeople(array $personIds): array
            {
                return [];
            }

            public function findByTeam(string $teamId): array
            {
                return [];
            }

            public function save(CalendarEvent $event): void
            {
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

        $tags = new class implements CalendarTagRepositoryInterface {
            public function find(Uuid $id): ?CalendarTag
            {
                return null;
            }

            public function visibleTo(Uuid $ownerId, ?Uuid $teamId): array
            {
                return [];
            }

            public function save(CalendarTag $tag): void
            {
            }
        };

        $access = new PlanningAccess($this->memberships());
        $expander = new OccurrenceExpander();
        $intervals = new BusyIntervals();
        $view = new CalendarView($events, $tags, $access, $expander, $intervals);
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event, ?string $eventName = null): object
            {
                return $event;
            }
        };

        return new DayPlanningService($events, $tags, $access, $expander, new ConflictDetector($view, $expander, $access, $intervals), new ConflictConfirmation('test-secret'), $dispatcher);
    }

    private function accountRepository(): SchoolAccountRepositoryInterface
    {
        return new class($this->account) implements SchoolAccountRepositoryInterface {
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

    private function importedLessons(): ImportedLessonRepositoryInterface
    {
        return new class implements ImportedLessonRepositoryInterface {
            private array $lessons = [];

            public function between(Uuid $accountId, string $from, string $to): array
            {
                return array_values($this->lessons);
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

    private function memberships(): TeamMembershipRepositoryInterface
    {
        return new class($this->admin, $this->team, $this->child) implements TeamMembershipRepositoryInterface {
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
                return [
                    new TeamMembership(Uuid::generate(), $this->team, $this->child, TeamRole::member(), new \DateTimeImmutable()),
                    new TeamMembership(Uuid::generate(), $this->team, $this->admin, TeamRole::admin(), new \DateTimeImmutable()),
                ];
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
}
