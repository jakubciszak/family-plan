<?php

declare(strict_types=1);

namespace App\Tests\DayPlanning;

use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Exception\PlanningException;
use App\DayPlanning\Domain\Service\OccurrenceExpander;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class OccurrenceExpanderTest extends TestCase
{
    public function testWeeklyRecurrencePreservesLocalTimeAcrossDst(): void
    {
        $event = $this->event('2026-10-19T08:00');
        $occurrences = $this->expand($event, '2026-10-18', '2026-11-03');
        self::assertSame(['2026-10-19T06:00:00+00:00', '2026-10-26T07:00:00+00:00', '2026-11-02T07:00:00+00:00'], array_map(static fn ($item) => $item->start->format(DATE_ATOM), $occurrences));
    }

    public function testMovedOccurrenceAppearsInNewRangeWithOriginalKey(): void
    {
        $event = $this->event('2026-09-21T08:00');
        $event->setException('2026-09-21T08:00', ['changes' => ['schedule' => ['kind' => 'TIMED', 'localStart' => '2026-12-01T10:00', 'durationMinutes' => 90, 'timeZone' => 'Europe/Warsaw']]]);
        $occurrences = $this->expand($event, '2026-12-01', '2026-12-02');
        self::assertCount(1, $occurrences);
        self::assertSame('2026-09-21T08:00', $occurrences[0]->key);
        self::assertSame('2026-12-01T09:00:00+00:00', $occurrences[0]->start->format(DATE_ATOM));
        self::assertCount(0, $this->expand($event, '2026-09-21', '2026-09-22'));
    }

    public function testEditingAnExceptionTitlePreservesItsMovedSchedule(): void
    {
        $event = $this->event('2026-09-21T08:00');
        $event->setException('2026-09-21T08:00', ['changes' => ['schedule' => ['kind' => 'TIMED', 'localStart' => '2026-12-01T10:00', 'durationMinutes' => 90, 'timeZone' => 'Europe/Warsaw'], 'location' => 'Szkoła']]);
        $event->setException('2026-09-21T08:00', ['changes' => ['title' => 'Nowy tytuł']]);
        $occurrence = $this->expand($event, '2026-12-01', '2026-12-02')[0];
        self::assertSame('2026-12-01T09:00:00+00:00', $occurrence->start->format(DATE_ATOM));
        self::assertSame('Nowy tytuł', $occurrence->definition['title']);
        self::assertSame('Szkoła', $occurrence->definition['location']);
    }

    public function testCancelledOccurrenceDoesNotCancelOtherDays(): void
    {
        $event = $this->event('2026-09-21T08:00');
        $event->setException('2026-09-21T08:00', ['cancelled' => true]);
        self::assertCount(1, $this->expand($event, '2026-09-21', '2026-09-29'));
    }

    public function testNonexistentOneOffTimeIsRejected(): void
    {
        $this->expectException(PlanningException::class);
        $this->event('2026-03-29T02:30', null);
    }

    public function testRecurringNonexistentTimeIsSkippedAndFirstAmbiguousTimeChosen(): void
    {
        $event = $this->event('2026-03-22T02:30', ['frequency' => 'WEEKLY', 'interval' => 1, 'byDay' => [7]]);
        self::assertCount(2, $this->expand($event, '2026-03-21', '2026-04-06'));
        $autumn = $this->expand($event, '2026-10-25', '2026-10-26');
        self::assertSame('2026-10-25T00:30:00+00:00', $autumn[0]->start->format(DATE_ATOM));
    }

    public function testAllDayUsesLocalDatesAcrossShortDstDay(): void
    {
        $event = CalendarEvent::create(Uuid::generate(), Uuid::generate(), ['title' => 'Dzień', 'schedule' => ['kind' => 'ALL_DAY', 'startDate' => '2026-03-29', 'endDate' => '2026-03-30', 'timeZone' => 'Europe/Warsaw']]);
        $occurrence = $this->expand($event, '2026-03-28', '2026-03-31')[0];
        self::assertSame(23 * 3600, $occurrence->end->getTimestamp() - $occurrence->start->getTimestamp());
    }

    public function testDeclineSurvivesEditingSeriesAndKeepsInvitation(): void
    {
        $owner = Uuid::generate();
        $person = Uuid::generate()->value();
        $event = CalendarEvent::create(Uuid::generate(), $owner, ['title' => 'Spotkanie', 'teamId' => Uuid::generate()->value(), 'participantIds' => [$person], 'schedule' => ['kind' => 'TIMED', 'localStart' => '2026-09-21T08:00', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw']]);
        $event->participate($person, 'DECLINED', null);
        $event->revise(['title' => 'Inny tytuł']);
        self::assertSame('DECLINED', $this->expand($event, '2026-09-21', '2026-09-22')[0]->participants[$person]);
    }

    public function testChangingScheduleWithExceptionsRequiresExplicitReset(): void
    {
        $event = $this->event('2026-09-21T08:00');
        $event->setException('2026-09-21T08:00', ['cancelled' => true]);
        $this->expectException(PlanningException::class);
        $this->expectExceptionMessage('exceptions_reset_required');
        $event->revise(['recurrence' => null]);
    }

    public function testHalfOpenIntervalsDoNotOverlapAtTouchingBoundary(): void
    {
        $event = $this->event('2026-09-21T08:00', null);
        self::assertCount(0, $this->expand($event, '2026-09-21T07:00:00Z', '2026-09-21T08:00:00Z'));
    }

    private function event(string $start, ?array $recurrence = ['frequency' => 'WEEKLY', 'interval' => 1, 'byDay' => [1]]): CalendarEvent
    {
        return CalendarEvent::create(Uuid::generate(), Uuid::generate(), ['title' => 'Test', 'schedule' => ['kind' => 'TIMED', 'localStart' => $start, 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw'], 'recurrence' => $recurrence]);
    }

    private function expand(CalendarEvent $event, string $from, string $to): array
    {
        return (new OccurrenceExpander())->expand($event, new \DateTimeImmutable($from), new \DateTimeImmutable($to));
    }
}
