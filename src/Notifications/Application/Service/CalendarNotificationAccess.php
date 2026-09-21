<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\DayPlanning\Domain\Repository\CalendarEventRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;

final readonly class CalendarNotificationAccess
{
    public function __construct(
        private CalendarEventRepositoryInterface $events,
        private TeamMembershipRepositoryInterface $memberships,
    ) {
    }

    public function allows(string $recipientId, array $parameters): bool
    {
        $metadata = $parameters['_calendar'] ?? null;
        if ($metadata === null) {
            return true;
        }
        $event = $this->events->find(Uuid::fromString($metadata['eventId']));
        if ($event === null) {
            return false;
        }
        $recipient = Uuid::fromString($recipientId);
        if ($event->ownerId()->equals($recipient)) {
            return true;
        }
        if ($event->teamId() === null || !$this->memberships->isMember($recipient, $event->teamId()) || !$this->memberships->isMember($event->ownerId(), $event->teamId())) {
            return false;
        }
        if (($metadata['removed'] ?? false) === true) {
            return true;
        }
        $key = $metadata['occurrenceKey'] ?? null;
        $invited = $key === null ? $event->allParticipantIds() : array_keys($event->participantsFor($key));

        return !$event->cancelled() && in_array($recipientId, $invited, true);
    }

    public static function publicParameters(array $parameters): array
    {
        unset($parameters['_calendar']);

        return $parameters;
    }
}
