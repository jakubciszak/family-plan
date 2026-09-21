<?php

declare(strict_types=1);

namespace App\Tests\DayPlanning;

use App\DayPlanning\Application\Query\CalendarView;
use App\DayPlanning\Application\Service\PlanningAccess;
use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Repository\CalendarEventRepositoryInterface;
use App\DayPlanning\Domain\Repository\CalendarTagRepositoryInterface;
use App\DayPlanning\Domain\Service\BusyIntervals;
use App\DayPlanning\Domain\Service\OccurrenceExpander;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CalendarViewTest extends TestCase
{
    public function testTagFiltersCannotRevealHiddenTagMembership(): void
    {
        $caller = Uuid::generate();
        $owner = Uuid::generate();
        $team = Uuid::generate();
        $tag = Uuid::generate();
        $event = CalendarEvent::create(Uuid::generate(), $owner, ['title' => 'Prywatne', 'teamId' => $team->value(), 'tagIds' => [$tag->value()], 'schedule' => ['kind' => 'TIMED', 'localStart' => '2026-09-21T08:00', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw']]);
        $view = $this->view([$event]);
        $query = ['from' => '2026-09-21T00:00:00Z', 'to' => '2026-09-22T00:00:00Z', 'teamId' => $team->value(), 'personIds' => [$owner->value()]];
        $plain = $view->calendar($caller, $query);
        $matching = $view->calendar($caller, $query + ['tagIds' => [$tag->value()]]);
        $nonMatching = $view->calendar($caller, $query + ['tagIds' => [Uuid::generate()->value()]]);
        self::assertSame($plain, $matching);
        self::assertSame($plain, $nonMatching);
        self::assertSame([], $plain['events']);
        self::assertCount(1, $plain['busy']);
        self::assertSame(['kind', 'personId', 'start', 'end'], array_keys($plain['busy'][0]));
    }

    public function testAvailabilityRemainsCompleteWhenVisibleEventsAreFilteredOut(): void
    {
        $owner = Uuid::generate();
        $event = CalendarEvent::create(Uuid::generate(), $owner, ['title' => 'Praca', 'schedule' => ['kind' => 'TIMED', 'localStart' => '2026-09-21T08:00', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw']]);
        $view = $this->view([$event]);
        $query = ['from' => '2026-09-21T00:00:00Z', 'to' => '2026-09-22T00:00:00Z', 'tagIds' => [Uuid::generate()->value()]];
        self::assertSame([], $view->calendar($owner, $query)['events']);
        self::assertCount(1, $view->availability($owner, $query)['busy']);
    }

    public function testDeclinedInvitationStillShowsDetailsWithoutBusyBlock(): void
    {
        $owner = Uuid::generate();
        $person = Uuid::generate();
        $event = CalendarEvent::create(Uuid::generate(), $owner, ['title' => 'Spotkanie', 'teamId' => Uuid::generate()->value(), 'participantIds' => [$person->value()], 'schedule' => ['kind' => 'TIMED', 'localStart' => '2026-09-21T08:00', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw']]);
        $event->participate($person->value(), 'DECLINED', null);
        $view = $this->view([$event]);
        $query = ['from' => '2026-09-21T00:00:00Z', 'to' => '2026-09-22T00:00:00Z'];
        self::assertSame('DECLINED', $view->calendar($person, $query)['events'][0]['participation']);
        self::assertSame([], $view->availability($person, $query)['busy']);
    }

    private function view(array $events): CalendarView
    {
        $repository = $this->createStub(CalendarEventRepositoryInterface::class);
        $repository->method('forPeople')->willReturn($events);
        $memberships = $this->createStub(TeamMembershipRepositoryInterface::class);
        $memberships->method('isMember')->willReturn(true);
        return new CalendarView($repository, $this->createStub(CalendarTagRepositoryInterface::class), new PlanningAccess($memberships), new OccurrenceExpander(), new BusyIntervals());
    }
}
