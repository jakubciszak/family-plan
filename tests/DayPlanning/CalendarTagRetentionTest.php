<?php

declare(strict_types=1);

namespace App\Tests\DayPlanning;

use App\DayPlanning\Application\Query\CalendarView;
use App\DayPlanning\Application\Service\ConflictConfirmation;
use App\DayPlanning\Application\Service\ConflictDetector;
use App\DayPlanning\Application\Service\DayPlanningService;
use App\DayPlanning\Application\Service\PlanningAccess;
use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Entity\CalendarTag;
use App\DayPlanning\Domain\Exception\PlanningException;
use App\DayPlanning\Domain\Repository\CalendarEventRepositoryInterface;
use App\DayPlanning\Domain\Repository\CalendarTagRepositoryInterface;
use App\DayPlanning\Domain\Service\BusyIntervals;
use App\DayPlanning\Domain\Service\OccurrenceExpander;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CalendarTagRetentionTest extends TestCase
{
    public function testDetachedOwnerCanKeepHistoricalTeamTagsWhileEditing(): void
    {
        [$event, $existing, $other, $service] = $this->fixture();
        $event->revokeMember($event->ownerId()->value(), $event->teamId()->value());
        $result = $service->update($event->ownerId(), $event->id(), $event->version(), ['title' => 'Nowy tytuł']);
        self::assertSame('Nowy tytuł', $result['title']);
        self::assertSame([$existing->id()->value()], $result['tagIds']);
        self::assertNull($result['teamId']);
    }

    public function testDetachedOwnerCannotAddAnotherForeignTeamTag(): void
    {
        [$event, $existing, $other, $service] = $this->fixture();
        $event->revokeMember($event->ownerId()->value(), $event->teamId()->value());
        $this->expectException(PlanningException::class);
        $service->update($event->ownerId(), $event->id(), $event->version(), ['tagIds' => [$existing->id()->value(), $other->id()->value()]]);
    }

    public function testArchivingATagDoesNotPreventEditingItsExistingEvent(): void
    {
        [$event, $existing, $other, $service] = $this->fixture();
        $existing->archive();
        $result = $service->update($event->ownerId(), $event->id(), $event->version(), ['title' => 'Zmiana']);
        self::assertSame([$existing->id()->value()], $result['tagIds']);
    }

    private function fixture(): array
    {
        $owner = Uuid::generate();
        $team = Uuid::generate();
        $tag = CalendarTag::create(Uuid::generate(), $owner, ['name' => 'Praca', 'scope' => 'TEAM', 'teamId' => $team->value()]);
        $other = CalendarTag::create(Uuid::generate(), $owner, ['name' => 'Inny', 'scope' => 'TEAM', 'teamId' => $team->value()]);
        $event = CalendarEvent::create(Uuid::generate(), $owner, ['title' => 'Plan', 'teamId' => $team->value(), 'tagIds' => [$tag->id()->value()], 'schedule' => ['kind' => 'TIMED', 'localStart' => '2026-09-21T08:00', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw']]);
        $events = $this->createStub(CalendarEventRepositoryInterface::class);
        $events->method('find')->willReturn($event);
        $events->method('forPeople')->willReturn([]);
        $events->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $tags = $this->createStub(CalendarTagRepositoryInterface::class);
        $tags->method('find')->willReturnCallback(static fn (Uuid $id) => $id->equals($tag->id()) ? $tag : $other);
        $memberships = $this->createStub(TeamMembershipRepositoryInterface::class);
        $memberships->method('isMember')->willReturn(true);
        $access = new PlanningAccess($memberships);
        $expander = new OccurrenceExpander();
        $intervals = new BusyIntervals();
        $view = new CalendarView($events, $tags, $access, $expander, $intervals);
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnArgument(0);
        return [$event, $tag, $other, new DayPlanningService($events, $tags, $access, $expander, new ConflictDetector($view, $expander, $access, $intervals), new ConflictConfirmation('test-secret'), $dispatcher)];
    }
}
