<?php

declare(strict_types=1);

namespace App\Notifications\Communication\EventSubscriber;

use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Event\EventChanged;
use App\DayPlanning\Domain\Repository\CalendarEventRepositoryInterface;
use App\Notifications\Application\Service\CalendarNotificationAccess;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\Repository\PersonalisationRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class CalendarEventChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CalendarEventRepositoryInterface $events,
        private CalendarNotificationAccess $access,
        private NotificationOrchestrator $notifications,
        private PersonalisationRepositoryInterface $personalisations,
        private ?LoggerInterface $logger = null,
        private ?ClockInterface $clock = null,
        private string $secret = '',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [EventChanged::class => 'onChanged'];
    }

    public function onChanged(EventChanged $change): void
    {
        try {
            $event = $this->events->find(Uuid::fromString($change->eventId));
        } catch (\Throwable $error) {
            $this->logFailure($change, null, $error);
            return;
        }
        if ($event === null) {
            return;
        }
        $recipients = $change->changeType === 'participation'
            ? [$event->ownerId()->value()]
            : array_unique([...$change->previousParticipantIds, ...$change->currentParticipantIds]);
        foreach ($recipients as $recipientId) {
            if ($recipientId === $change->actorId) {
                continue;
            }
            try {
                $this->notifyRecipient($event, $change, $recipientId);
            } catch (\Throwable $error) {
                $this->logFailure($change, $recipientId, $error);
            }
        }
    }

    private function notifyRecipient(CalendarEvent $event, EventChanged $change, string $recipientId): void
    {
        $removed = $event->cancelled() || !in_array($recipientId, $change->currentParticipantIds, true);
        $parameters = [
            'url' => '/day-planning',
            // One place per event, so a newer update replaces the older one. Calendar notifications stay
            // neutral, so the tag cannot be traced back to the event.
            'tag' => 'calendar-' . substr(hash_hmac('sha256', $change->eventId, $this->secret), 0, 24),
            // "Your calendar has an update" is old news after a couple of days; the calendar shows the rest.
            'expires_at' => ($this->clock?->now() ?? new \DateTimeImmutable())->modify('+2 days')->format(DATE_ATOM),
            '_calendar' => ['eventId' => $change->eventId, 'occurrenceKey' => $change->occurrenceKey, 'removed' => $removed],
        ];
        if (!$this->access->allows($recipientId, $parameters)) {
            return;
        }
        $recipient = Uuid::fromString($recipientId);
        $english = $this->personalisations->ofUser($recipient)?->language() === 'en';
        $message = $removed
            ? ($english ? 'A calendar event was cancelled or your invitation was removed.' : 'Wydarzenie zostało odwołane lub Twoje zaproszenie zostało usunięte.')
            : ($english ? 'Your calendar has an update. Open Day plan to see it.' : 'Masz aktualizację w kalendarzu. Otwórz Plan dnia, aby ją zobaczyć.');
        $this->notifications->notifyUser(
            NotificationEvent::fromString($removed ? NotificationEvent::CALENDAR_REMOVED : NotificationEvent::CALENDAR_CHANGED),
            $recipient,
            $message,
            $english ? 'Day plan' : 'Plan dnia',
            $parameters,
        );
    }

    private function logFailure(EventChanged $change, ?string $recipientId, \Throwable $error): void
    {
        $this->logger?->error('Calendar notification delivery failed after event commit', [
            'event_id' => $change->eventId,
            'recipient_id' => $recipientId,
            'change_type' => $change->changeType,
            'version' => $change->version,
            'error_type' => $error::class,
        ]);
    }
}
