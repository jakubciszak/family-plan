<?php

declare(strict_types=1);

namespace App\Tests\DayPlanningIntegration;

use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Repository\CalendarEventRepositoryInterface;
use App\Notifications\Application\Service\CalendarNotificationAccess;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CalendarNotificationAccessTest extends TestCase
{
    public function testQueuedNotificationIsDroppedWhenPrivateInvitationWasRevoked(): void
    {
        $owner = Uuid::generate();
        $guest = Uuid::generate();
        $event = CalendarEvent::create(Uuid::generate(), $owner, ['title' => 'Private', 'teamId' => Uuid::generate()->value(), 'visibility' => 'PRIVATE', 'schedule' => ['kind' => 'TIMED', 'localStart' => '2026-09-21T10:00', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw'], 'participantIds' => [$guest->value()]]);
        $events = $this->createStub(CalendarEventRepositoryInterface::class);
        $events->method('find')->willReturn($event);
        $memberships = $this->createStub(TeamMembershipRepositoryInterface::class);
        $memberships->method('isMember')->willReturn(true);
        $access = new CalendarNotificationAccess($events, $memberships);
        $parameters = ['_calendar' => ['eventId' => $event->id()->value(), 'occurrenceKey' => null, 'removed' => false]];
        self::assertTrue($access->allows($guest->value(), $parameters));
        $event->revise(['participantIds' => []]);
        self::assertFalse($access->allows($guest->value(), $parameters));
    }

    public function testNotificationCannotOutliveTeamMembership(): void
    {
        $owner = Uuid::generate();
        $guest = Uuid::generate();
        $event = CalendarEvent::create(Uuid::generate(), $owner, ['title' => 'Private', 'teamId' => Uuid::generate()->value(), 'schedule' => ['kind' => 'TIMED', 'localStart' => '2026-09-21T10:00', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw'], 'participantIds' => [$guest->value()]]);
        $events = $this->createStub(CalendarEventRepositoryInterface::class);
        $events->method('find')->willReturn($event);
        $memberships = $this->createStub(TeamMembershipRepositoryInterface::class);
        $memberships->method('isMember')->willReturn(false);
        self::assertFalse((new CalendarNotificationAccess($events, $memberships))->allows($guest->value(), ['_calendar' => ['eventId' => $event->id()->value(), 'removed' => false]]));
    }

    public function testInternalCalendarMetadataNeverReachesDevices(): void
    {
        self::assertSame(['url' => '/day-planning'], CalendarNotificationAccess::publicParameters(['url' => '/day-planning', '_calendar' => ['eventId' => 'hidden']]));
    }
}
