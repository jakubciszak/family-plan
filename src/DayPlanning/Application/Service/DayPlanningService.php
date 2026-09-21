<?php

declare(strict_types=1);

namespace App\DayPlanning\Application\Service;

use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Event\EventChanged;
use App\DayPlanning\Domain\Exception\PlanningException;
use App\DayPlanning\Domain\Repository\CalendarEventRepositoryInterface;
use App\DayPlanning\Domain\Repository\CalendarTagRepositoryInterface;
use App\DayPlanning\Domain\Service\OccurrenceExpander;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class DayPlanningService
{
    public function __construct(private CalendarEventRepositoryInterface $events, private CalendarTagRepositoryInterface $tags, private PlanningAccess $access, private OccurrenceExpander $expander, private ConflictDetector $conflicts, private ConflictConfirmation $confirmation, private EventDispatcherInterface $dispatcher)
    {
    }

    public function create(Uuid $caller, array $data, ?string $idempotencyKey): array
    {
        if ($idempotencyKey !== null) {
            $idempotencyKey = CalendarEvent::uuid($idempotencyKey);
        }
        $candidate = CalendarEvent::create(Uuid::generate(), $caller, $data);
        $hash = $this->confirmation->hash($candidate->definition());
        $notification = null;
        $result = $this->events->transactional(function () use ($caller, $data, $idempotencyKey, $candidate, $hash, &$notification): array {
            if ($idempotencyKey !== null) {
                $previous = $this->events->idempotentResult($caller->value(), $idempotencyKey, $hash);
                if ($previous !== null) {
                    return $previous;
                }
            }
            $this->validate($candidate, $caller);
            $this->checkConflicts($candidate, $caller, $data['conflictConfirmation'] ?? null, creating: true);
            $this->events->save($candidate);
            $result = $candidate->describe() + ['conflictCoverage' => $this->conflicts->coverage($candidate)];
            if ($idempotencyKey !== null) {
                $this->events->rememberResult($caller->value(), $idempotencyKey, $hash, $result);
            }
            $notification = new EventChanged($candidate->id()->value(), $caller->value(), [], $candidate->allParticipantIds(), 'created', $candidate->version());
            return $result;
        });
        $this->notify($notification);
        return $result;
    }

    public function definition(Uuid $caller, Uuid $id): array
    {
        return $this->owned($caller, $id)->describe();
    }

    public function update(Uuid $caller, Uuid $id, int $version, array $data): array
    {
        return $this->mutate($caller, $id, $version, $data, static fn (CalendarEvent $event) => $event->revise($data), 'updated');
    }

    public function cancel(Uuid $caller, Uuid $id, int $version): void
    {
        $this->mutate($caller, $id, $version, [], static fn (CalendarEvent $event) => $event->cancel(), 'cancelled', checkConflicts: false);
    }

    public function exception(Uuid $caller, Uuid $id, int $version, string $key, array $data): array
    {
        return $this->mutate($caller, $id, $version, $data, function (CalendarEvent $event) use ($key, $data): void {
            if (!$this->expander->validKey($event, $key)) {
                throw PlanningException::notFound();
            }
            if (count($event->exceptions()) >= 500 && !isset($event->exceptions()[$key])) {
                throw PlanningException::invalid('A series can contain at most 500 exceptions.');
            }
            $event->setException($key, $data);
        }, ($data['cancelled'] ?? false) === true ? 'occurrence_cancelled' : 'updated', $key);
    }

    public function restore(Uuid $caller, Uuid $id, int $version, string $key, array $data = []): array
    {
        return $this->mutate($caller, $id, $version, $data, function (CalendarEvent $event) use ($key): void {
            if (!$this->expander->validKey($event, $key)) {
                throw PlanningException::notFound();
            }
            $event->restoreException($key);
        }, 'updated', $key);
    }

    private function mutate(Uuid $caller, Uuid $id, int $version, array $data, callable $mutation, string $type, ?string $key = null, bool $checkConflicts = true): array
    {
        $notification = null;
        $result = $this->events->transactional(function () use ($caller, $id, $version, $data, $mutation, $type, $key, $checkConflicts, &$notification): array {
            $event = $this->owned($caller, $id);
            if ($event->version() !== $version) {
                throw new PlanningException('version_conflict', 412);
            }
            $candidate = clone $event;
            $mutation($candidate);
            if ($type !== 'cancelled') {
                $this->validate($candidate, $caller, $event);
            }
            if ($checkConflicts) {
                $this->checkConflicts($candidate, $caller, $data['conflictConfirmation'] ?? null, $key);
            }
            $before = $key === null ? $event->allParticipantIds() : array_keys($event->participantsFor($key));
            $mutation($event);
            $this->events->save($event);
            $notification = new EventChanged($id->value(), $caller->value(), $before, in_array($type, ['cancelled', 'occurrence_cancelled'], true) ? [] : ($key === null ? $event->allParticipantIds() : array_keys($event->participantsFor($key))), $type, $event->version(), $key);
            return $event->describe() + ['conflictCoverage' => $this->conflicts->coverage($event, $key)];
        });
        $this->notify($notification);
        return $result;
    }

    public function participate(Uuid $caller, Uuid $id, array $data): array
    {
        $status = $data['status'] ?? null;
        $key = $data['occurrenceKey'] ?? null;
        if (!is_string($status) || !in_array($status, ['INCLUDED', 'DECLINED'], true) || ($key !== null && !is_string($key))) {
            throw PlanningException::invalid('Invalid participation.');
        }
        $notification = null;
        $result = $this->events->transactional(function () use ($caller, $id, $status, $key, $data, &$notification): array {
            $event = $this->events->find($id);
            if ($event === null || $event->cancelled()) {
                throw PlanningException::notFound();
            }
            if ($key !== null) {
                $occurrence = $this->expander->occurrence($event, $key);
                if ($occurrence === null || !$this->access->canParticipate($occurrence, $caller)) {
                    throw PlanningException::notFound();
                }
            } else {
                $base = $event->describe();
                if (!in_array($caller->value(), $base['participantIds'], true)) {
                    throw PlanningException::notFound();
                }
                if (!$event->ownerId()->equals($caller)) {
                    $this->access->assertTeam($caller, $event->teamId()?->value());
                }
            }
            $candidate = clone $event;
            $candidate->participate($caller->value(), $status, $key);
            if ($status === 'INCLUDED') {
                $this->checkConflicts($candidate, $caller, $data['conflictConfirmation'] ?? null, $key);
            }
            $event->participate($caller->value(), $status, $key);
            $this->events->save($event);
            $notification = new EventChanged($id->value(), $caller->value(), $event->allParticipantIds(), $event->allParticipantIds(), 'participation', $event->version(), $key);
            return ['status' => $status, 'occurrenceKey' => $key];
        });
        $this->notify($notification);
        return $result;
    }

    private function owned(Uuid $caller, Uuid $id): CalendarEvent
    {
        $event = $this->events->find($id);
        if ($event === null || !$event->ownerId()->equals($caller) || $event->cancelled()) {
            throw PlanningException::notFound();
        }
        return $event;
    }

    private function validate(CalendarEvent $event, Uuid $caller, ?CalendarEvent $previous = null): void
    {
        $definition = $event->definition();
        $retained = $previous?->definition()['tagIds'] ?? [];
        foreach ($previous?->exceptions() ?? [] as $exception) {
            $retained = array_merge($retained, $exception['changes']['tagIds'] ?? []);
        }
        $this->validateDefinition($definition, $caller, $retained);
        foreach ($event->exceptions() as $exception) {
            if (isset($exception['changes'])) {
                $this->validateDefinition(array_replace($definition, $exception['changes']), $caller, $retained);
            }
        }
    }

    private function validateDefinition(array $definition, Uuid $caller, array $retained): void
    {
        if (($definition['ownerParticipates'] ?? true) === false) {
            $this->access->assertManagesTeam($caller, $definition['teamId']);
        }
        $this->access->people($caller, $definition['teamId'], $definition['participantIds']);
        foreach ($definition['tagIds'] as $id) {
            $tag = $this->tags->find(Uuid::fromString($id));
            if ($tag === null || ($tag->archived() && !in_array($id, $retained, true))) {
                throw PlanningException::invalid('Unknown or archived tag.');
            }
            $valid = $tag->scope() === 'PERSONAL'
                ? $definition['visibility'] === 'PRIVATE' && $tag->ownerId()->equals($caller)
                : ($tag->teamId()?->value() === $definition['teamId'] || ($definition['teamId'] === null && $definition['visibility'] === 'PRIVATE' && in_array($id, $retained, true)));
            if (!$valid) {
                throw PlanningException::invalid('The tag does not belong to the event scope.');
            }
        }
    }

    private function checkConflicts(CalendarEvent $candidate, Uuid $caller, mixed $token, ?string $key = null, bool $creating = false): void
    {
        $conflicts = $this->conflicts->conflicts($candidate, $key);
        $definition = $candidate->confirmationState() + ['target' => $creating ? 'create' : $candidate->id()->value(), 'occurrenceKey' => $key];
        if ($conflicts !== [] && !$this->confirmation->valid($token, $caller->value(), $definition, $conflicts)) {
            throw new PlanningException('planning_conflict', 409, ['conflicts' => $conflicts, 'confirmationToken' => $this->confirmation->issue($caller->value(), $definition, $conflicts), 'conflictCoverage' => $this->conflicts->coverage($candidate, $key)]);
        }
    }

    private function notify(?EventChanged $notification): void
    {
        if ($notification !== null) {
            $this->dispatcher->dispatch($notification);
        }
    }
}
