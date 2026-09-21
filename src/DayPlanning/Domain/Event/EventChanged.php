<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\Event;

final readonly class EventChanged
{
    public function __construct(public string $eventId, public string $actorId, public array $previousParticipantIds, public array $currentParticipantIds, public string $changeType, public int $version, public ?string $occurrenceKey = null)
    {
    }
}
