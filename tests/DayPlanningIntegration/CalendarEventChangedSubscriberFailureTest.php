<?php

declare(strict_types=1);

namespace App\Tests\DayPlanningIntegration;

use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Event\EventChanged;
use App\DayPlanning\Domain\Repository\CalendarEventRepositoryInterface;
use App\Notifications\Application\Service\CalendarNotificationAccess;
use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Communication\Application\Service\NotificationPolicyProvider;
use App\Notifications\Communication\Domain\Repository\NotificationPolicyRepositoryInterface;
use App\Notifications\Communication\Domain\Service\ChannelResolver;
use App\Notifications\Communication\EventSubscriber\CalendarEventChangedSubscriber;
use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserSettings\Domain\Repository\PersonalisationRepositoryInterface;
use App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CalendarEventChangedSubscriberFailureTest extends TestCase
{
    public function testEventLookupFailureDoesNotEscapeMutationObserverOrLogPrivateDetails(): void
    {
        $events = $this->createStub(CalendarEventRepositoryInterface::class);
        $events->method('find')->willThrowException(new \RuntimeException('Secret appointment title in database error'));
        $personalisation = $this->createStub(PersonalisationRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Calendar notification delivery failed after event commit',
            self::callback(static fn (array $context): bool => $context['error_type'] === \RuntimeException::class && $context['recipient_id'] === null && !str_contains(json_encode($context), 'Secret appointment')),
        );
        $eventId = Uuid::generate()->value();
        $actorId = Uuid::generate()->value();
        $this->subscriber($events, $personalisation, $logger)->onChanged(new EventChanged($eventId, $actorId, [], [], 'created', 1));
    }

    public function testOneRecipientFailureDoesNotPreventAttemptingNextRecipient(): void
    {
        [$event, $first, $second] = $this->event();
        $events = $this->createStub(CalendarEventRepositoryInterface::class);
        $events->method('find')->willReturn($event);
        $personalisation = $this->createMock(PersonalisationRepositoryInterface::class);
        $personalisation->expects(self::exactly(2))->method('ofUser')->willReturnCallback(static function (Uuid $id) use ($first) {
            if ($id->equals($first)) {
                throw new \RuntimeException('Unavailable preference store');
            }
            return null;
        });
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findById')->with(self::callback(static fn (Uuid $id): bool => $id->equals($second)))->willReturn(null);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::anything(), self::callback(static fn (array $context): bool => $context['recipient_id'] === $first->value() && $context['error_type'] === \RuntimeException::class));
        $this->subscriber($events, $personalisation, $logger, $users)->onChanged(new EventChanged($event->id()->value(), $event->ownerId()->value(), [], $event->allParticipantIds(), 'created', 1));
    }

    public function testOrchestrationFailureDoesNotEscapeAndNextRecipientStillRuns(): void
    {
        [$event, $first, $second] = $this->event();
        $events = $this->createStub(CalendarEventRepositoryInterface::class);
        $events->method('find')->willReturn($event);
        $personalisation = $this->createStub(PersonalisationRepositoryInterface::class);
        $policies = $this->createMock(NotificationPolicyRepositoryInterface::class);
        $attempts = 0;
        $policies->expects(self::exactly(2))->method('findByEvent')->willReturnCallback(static function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new \RuntimeException('Unavailable notification policy store');
            }
            return null;
        });
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('findById')->with(self::callback(static fn (Uuid $id): bool => $id->equals($second)))->willReturn(null);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $this->subscriber($events, $personalisation, $logger, $users, $policies)->onChanged(new EventChanged($event->id()->value(), $event->ownerId()->value(), [], $event->allParticipantIds(), 'created', 1));
    }

    private function subscriber(CalendarEventRepositoryInterface $events, PersonalisationRepositoryInterface $personalisation, LoggerInterface $logger, ?UserRepositoryInterface $users = null, ?NotificationPolicyRepositoryInterface $policies = null): CalendarEventChangedSubscriber
    {
        $memberships = $this->createStub(TeamMembershipRepositoryInterface::class);
        $memberships->method('isMember')->willReturn(true);
        $notifications = new NotificationOrchestrator(
            new NotificationFacade([]),
            $users ?? $this->createStub(UserRepositoryInterface::class),
            $this->createStub(UserSettingsRepositoryInterface::class),
            new NotificationPolicyProvider($policies ?? $this->createStub(NotificationPolicyRepositoryInterface::class)),
            new ChannelResolver(),
        );
        return new CalendarEventChangedSubscriber($events, new CalendarNotificationAccess($events, $memberships), $notifications, $personalisation, $logger);
    }

    private function event(): array
    {
        $owner = Uuid::generate();
        $first = Uuid::generate();
        $second = Uuid::generate();
        $event = CalendarEvent::create(Uuid::generate(), $owner, ['title' => 'Private appointment', 'teamId' => Uuid::generate()->value(), 'participantIds' => [$first->value(), $second->value()], 'schedule' => ['kind' => 'TIMED', 'localStart' => '2026-09-21T10:00', 'durationMinutes' => 60, 'timeZone' => 'Europe/Warsaw']]);
        return [$event, $first, $second];
    }
}
