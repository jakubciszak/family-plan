<?php

declare(strict_types=1);

namespace App\Tests\DayPlanning;

use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Exception\PlanningException;
use App\DayPlanning\Domain\Service\OccurrenceExpander;
use App\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class RecurrenceRuleTest extends TestCase
{
    public function testWeeklyIntervalUsesIsoWeeksAndInclusiveEndDate(): void
    {
        $event = $this->event(['frequency' => 'WEEKLY', 'interval' => 2, 'byDay' => [1, 3], 'until' => '2026-10-05']);
        self::assertSame(['2026-09-23T08:00', '2026-10-05T08:00'], $this->keys($event, '2026-09-23', '2026-10-07'));
    }

    public function testCountIsCountedBeforeVisibleRangeAndSkippedDstDateDoesNotConsumeIt(): void
    {
        $event = $this->event(['frequency' => 'DAILY', 'count' => 3], '2026-03-28T02:30');
        self::assertSame(['2026-03-30T02:30', '2026-03-31T02:30'], $this->keys($event, '2026-03-30', '2026-04-05'));
    }

    public function testSecondAmbiguousOccurrenceCanBeSelectedWithAnOffset(): void
    {
        $event = $this->event(null, '2026-10-25T02:30');
        $event->revise(['schedule' => ['kind' => 'TIMED', 'localStart' => '2026-10-25T02:30', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw', 'utcOffset' => '+01:00']]);
        self::assertSame('2026-10-25T01:30:00+00:00', (new OccurrenceExpander())->occurrence($event, 'single')->start->format(DATE_ATOM));
    }

    public function testInvalidCalendarDateCannotBeNormalizedIntoAnotherDay(): void
    {
        $this->expectException(PlanningException::class);
        $this->event(null, '2026-02-30T08:00');
    }

    public function testOwnerCanDeclineWithoutGivingUpOwnership(): void
    {
        $event = $this->event(null);
        $owner = $event->ownerId();
        $event->participate($owner->value(), 'DECLINED', null);
        self::assertTrue($event->ownerId()->equals($owner));
        self::assertSame([], (new OccurrenceExpander())->occurrence($event, 'single')->includedIds());
    }

    public function testDecliningSingleOccurrenceDoesNotChangeOtherDays(): void
    {
        $event = $this->event(['frequency' => 'DAILY', 'count' => 2]);
        $owner = $event->ownerId()->value();
        $event->participate($owner, 'DECLINED', '2026-09-23T08:00');
        self::assertSame('DECLINED', $event->participantsFor('2026-09-23T08:00')[$owner]);
        self::assertSame('INCLUDED', $event->participantsFor('2026-09-24T08:00')[$owner]);
    }

    public function testInvalidOriginalKeyCannotAddressAnException(): void
    {
        $event = $this->event(['frequency' => 'WEEKLY', 'byDay' => [3], 'count' => 2]);
        self::assertFalse((new OccurrenceExpander())->validKey($event, '2026-09-24T08:00'));
        self::assertFalse((new OccurrenceExpander())->validKey($event, '2026-10-07T08:00'));
    }

    private function event(?array $rule, string $start = '2026-09-23T08:00'): CalendarEvent
    {
        return CalendarEvent::create(Uuid::generate(), Uuid::generate(), ['title' => 'Test', 'schedule' => ['kind' => 'TIMED', 'localStart' => $start, 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw'], 'recurrence' => $rule]);
    }

    private function keys(CalendarEvent $event, string $from, string $to): array
    {
        return array_map(static fn ($item): string => $item->key, (new OccurrenceExpander())->expand($event, new \DateTimeImmutable($from), new \DateTimeImmutable($to)));
    }
}
