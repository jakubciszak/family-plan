<?php

declare(strict_types=1);

namespace App\DayPlanning\Application\Query;

use App\DayPlanning\Application\Service\PlanningAccess;
use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Exception\PlanningException;
use App\DayPlanning\Domain\Repository\CalendarEventRepositoryInterface;
use App\DayPlanning\Domain\Repository\CalendarTagRepositoryInterface;
use App\DayPlanning\Domain\Service\BusyIntervals;
use App\DayPlanning\Domain\Service\OccurrenceExpander;
use App\DayPlanning\Domain\ValueObject\EventOccurrence;
use App\DayPlanning\Domain\ValueObject\QueryRange;
use App\Shared\Domain\ValueObject\Uuid;

final readonly class CalendarView
{
    public function __construct(private CalendarEventRepositoryInterface $events, private CalendarTagRepositoryInterface $tags, private PlanningAccess $access, private OccurrenceExpander $expander, private BusyIntervals $intervals)
    {
    }

    public function calendar(Uuid $caller, array $query): array
    {
        $range = QueryRange::fromStrings($query['from'] ?? null, $query['to'] ?? null);
        $people = $this->access->people($caller, $query['teamId'] ?? null, $query['personIds'] ?? []);
        $tags = CalendarEvent::uuidList($query['tagIds'] ?? [], 20);
        $visible = [];
        $busy = [];
        foreach ($this->occurrences($people, $range) as $occurrence) {
            if (array_intersect(array_keys($occurrence->participants), $people) === []) {
                continue;
            }
            if ($this->access->canRead($occurrence, $caller)) {
                if ($tags === [] || array_intersect($tags, $occurrence->definition['tagIds']) !== []) {
                    $visible[] = $this->describe($occurrence, $caller);
                }
            } else {
                $busy = array_merge($busy, $this->busyOf($occurrence, $people, $range));
            }
        }
        usort($visible, static fn (array $a, array $b): int => [$a['start'], $a['id'], $a['occurrenceKey']] <=> [$b['start'], $b['id'], $b['occurrenceKey']]);
        return ['events' => $visible, 'busy' => $this->intervals->merge($busy), 'coverage' => $range->coverage()];
    }

    public function occurrence(Uuid $caller, Uuid $id, string $key): array
    {
        $event = $this->events->find($id);
        $occurrence = $event === null ? null : $this->expander->occurrence($event, $key);
        if ($occurrence === null || !$this->access->canRead($occurrence, $caller)) {
            throw PlanningException::notFound();
        }
        return $this->describe($occurrence, $caller);
    }

    public function availability(Uuid $caller, array $query): array
    {
        $range = QueryRange::fromStrings($query['from'] ?? null, $query['to'] ?? null);
        $people = $this->access->people($caller, $query['teamId'] ?? null, $query['personIds'] ?? [], true);
        return ['busy' => $this->busy($people, $range), 'personIds' => $people, 'coverage' => $range->coverage()];
    }

    public function busy(array $people, QueryRange $range, ?string $excludeEventId = null): array
    {
        $busy = [];
        foreach ($this->occurrences($people, $range) as $occurrence) {
            if ($occurrence->event->id()->value() !== $excludeEventId) {
                $busy = array_merge($busy, $this->busyOf($occurrence, $people, $range));
            }
        }
        return $this->intervals->merge($busy);
    }

    public function occurrences(array $people, QueryRange $range): array
    {
        $result = [];
        $events = $this->events->forPeople($people);
        if (count($events) > 2000) {
            throw new PlanningException('range_too_complex', 422, [], 'Please select fewer participants.');
        }
        foreach ($events as $event) {
            $result = array_merge($result, $this->expander->expand($event, $range->from, $range->to));
            if (count($result) > 20000) {
                throw new PlanningException('range_too_complex', 422, [], 'Please narrow the calendar range.');
            }
        }
        return $result;
    }

    public function busyOf(EventOccurrence $occurrence, array $people, QueryRange $range): array
    {
        if (!$occurrence->definition['blocksTime']) {
            return [];
        }
        $result = [];
        foreach (array_intersect($this->access->activeParticipants($occurrence), $people) as $personId) {
            $result[] = ['kind' => 'busy', 'personId' => $personId, 'start' => max($range->from, $occurrence->start)->format(DATE_ATOM), 'end' => min($range->to, $occurrence->end)->format(DATE_ATOM)];
        }
        return $result;
    }

    public function describe(EventOccurrence $occurrence, Uuid $caller): array
    {
        $event = $occurrence->event;
        $data = $occurrence->definition;
        $tags = [];
        foreach ($data['tagIds'] as $id) {
            $tag = $this->tags->find(Uuid::fromString($id));
            if ($tag !== null) {
                $tags[] = array_intersect_key($tag->describe(), array_flip(['id', 'name', 'color']));
            }
        }
        return [
            'id' => $event->id()->value(), 'occurrenceKey' => $occurrence->key,
            'title' => $data['title'], 'description' => $data['description'], 'location' => $data['location'],
            'ownerId' => $event->ownerId()->value(), 'teamId' => $data['teamId'], 'visibility' => $data['visibility'],
            'start' => $occurrence->start->format(DATE_ATOM), 'end' => $occurrence->end->format(DATE_ATOM),
            'allDay' => $data['schedule']['kind'] === 'ALL_DAY', 'timeZone' => $data['schedule']['timeZone'],
            'participantIds' => array_keys($occurrence->participants), 'participants' => CalendarEvent::participantList($occurrence->participants),
            'tags' => $tags, 'blocksTime' => $data['blocksTime'], 'recurring' => $data['recurrence'] !== null,
            'canEdit' => $event->ownerId()->equals($caller), 'canChangeParticipation' => $this->access->canParticipate($occurrence, $caller),
            'participation' => $occurrence->participants[$caller->value()] ?? null, 'version' => $event->version(),
        ];
    }
}
