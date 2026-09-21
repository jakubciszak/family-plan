<?php

declare(strict_types=1);

namespace App\DayPlanning\Application\Service;

use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\DayPlanning\Domain\Entity\CalendarTag;
use App\DayPlanning\Domain\Exception\PlanningException;
use App\DayPlanning\Domain\ValueObject\EventOccurrence;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;

final readonly class PlanningAccess
{
    public function __construct(private TeamMembershipRepositoryInterface $memberships)
    {
    }

    public function assertTeam(Uuid $caller, mixed $teamId): void
    {
        if ($teamId !== null && !is_string($teamId)) {
            throw PlanningException::invalid('Invalid team identifier.');
        }
        if ($teamId !== null && !$this->memberships->isMember($caller, Uuid::fromString(CalendarEvent::uuid($teamId)))) {
            throw new PlanningException('access_denied', 403);
        }
    }

    public function people(Uuid $caller, mixed $teamId, mixed $ids, bool $includeCaller = false): array
    {
        $this->assertTeam($caller, $teamId);
        $people = CalendarEvent::uuidList($ids, 50);
        if ($people === [] || $includeCaller) {
            $people = array_values(array_unique(array_merge([$caller->value()], $people)));
        }
        foreach ($people as $id) {
            if ($id !== $caller->value() && ($teamId === null || !$this->memberships->isMember(Uuid::fromString($id), Uuid::fromString($teamId)))) {
                throw new PlanningException('access_denied', 403);
            }
        }
        return $people;
    }

    public function canRead(EventOccurrence $occurrence, Uuid $caller): bool
    {
        if ($occurrence->event->ownerId()->equals($caller)) {
            return true;
        }
        $team = $occurrence->event->teamId();
        return $team !== null && $this->memberships->isMember($caller, $team)
            && $this->memberships->isMember($occurrence->event->ownerId(), $team)
            && ($occurrence->definition['visibility'] === 'TEAM' || array_key_exists($caller->value(), $occurrence->participants));
    }

    public function canParticipate(EventOccurrence $occurrence, Uuid $caller): bool
    {
        return array_key_exists($caller->value(), $occurrence->participants) && $this->canRead($occurrence, $caller);
    }

    public function activeParticipants(EventOccurrence $occurrence): array
    {
        return array_values(array_filter($occurrence->includedIds(), fn (string $id): bool => $id === $occurrence->event->ownerId()->value() || ($occurrence->event->teamId() !== null && $this->memberships->isMember(Uuid::fromString($id), $occurrence->event->teamId()) && $this->memberships->isMember($occurrence->event->ownerId(), $occurrence->event->teamId()))));
    }

    public function canManageTag(CalendarTag $tag, Uuid $caller): bool
    {
        return $tag->scope() === 'PERSONAL' ? $tag->ownerId()->equals($caller) : ($tag->teamId() !== null && $this->memberships->isMember($caller, $tag->teamId()) && $this->memberships->isAdmin($caller, $tag->teamId()));
    }
}
