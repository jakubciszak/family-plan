<?php

declare(strict_types=1);

namespace App\Tests\DayPlanningIntegration;

use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Repository\CalendarEventRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Infrastructure\Persistence\CalendarAwareTeamMemberships;
use PHPUnit\Framework\TestCase;

final class CalendarMembershipRevocationTest extends TestCase
{
    public function testDepartureRevokesAllOccurrenceInvitationsInsideTheMembershipTransaction(): void
    {
        $team = Uuid::generate();
        $owner = Uuid::generate();
        $guest = Uuid::generate();
        $event = CalendarEvent::create(Uuid::generate(), $owner, ['title' => 'Private', 'teamId' => $team->value(), 'visibility' => 'PRIVATE', 'schedule' => ['kind' => 'TIMED', 'localStart' => '2026-09-21T10:00', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw'], 'participantIds' => [$guest->value()]]);
        $memberships = $this->createMock(TeamMembershipRepositoryInterface::class);
        $events = $this->createMock(CalendarEventRepositoryInterface::class);
        $events->expects(self::once())->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
        $memberships->expects(self::once())->method('leave')->with($team, $guest);
        $events->method('findByTeam')->with($team->value())->willReturn([$event]);
        $events->expects(self::once())->method('save')->with($event);

        (new CalendarAwareTeamMemberships($memberships, $events))->leave($team, $guest);

        self::assertNotContains($guest->value(), $event->allParticipantIds());
        self::assertContains($owner->value(), $event->allParticipantIds());
    }
}
