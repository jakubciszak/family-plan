<?php

declare(strict_types=1);

namespace App\Tests\DayPlanning;

use App\DayPlanning\Application\Service\PlanningAccess;
use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Service\OccurrenceExpander;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class PlanningAccessTest extends TestCase
{
    public function testMemberAndAdministratorDoNotSeePrivateDetails(): void
    {
        $memberships = $this->createStub(TeamMembershipRepositoryInterface::class);
        $memberships->method('isMember')->willReturn(true);
        $memberships->method('isAdmin')->willReturn(true);
        $access = new PlanningAccess($memberships);
        $event = $this->event();
        $occurrence = (new OccurrenceExpander())->occurrence($event, 'single');
        self::assertFalse($access->canRead($occurrence, Uuid::generate()));
        self::assertTrue($access->canRead($occurrence, $event->ownerId()));
    }

    public function testOnlyOccurrenceInviteeCannotReadOtherOccurrence(): void
    {
        $memberships = $this->createStub(TeamMembershipRepositoryInterface::class);
        $memberships->method('isMember')->willReturn(true);
        $access = new PlanningAccess($memberships);
        $invitee = Uuid::generate();
        $event = $this->event();
        $event->revise(['recurrence' => ['frequency' => 'DAILY', 'count' => 2]]);
        $event->setException('2026-09-21T08:00', ['changes' => ['participantIds' => [$invitee->value()]]]);
        $expander = new OccurrenceExpander();
        self::assertTrue($access->canRead($expander->occurrence($event, '2026-09-21T08:00'), $invitee));
        self::assertFalse($access->canRead($expander->occurrence($event, '2026-09-22T08:00'), $invitee));
    }

    private function event(): CalendarEvent
    {
        return CalendarEvent::create(Uuid::generate(), Uuid::generate(), ['title' => 'Tajemnica', 'teamId' => Uuid::generate()->value(), 'schedule' => ['kind' => 'TIMED', 'localStart' => '2026-09-21T08:00', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw']]);
    }
}
