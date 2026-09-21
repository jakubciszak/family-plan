<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\ValueObject;

use App\DayPlanning\Domain\Entity\CalendarEvent;

final readonly class EventOccurrence
{
    public function __construct(public CalendarEvent $event, public string $key, public \DateTimeImmutable $start, public \DateTimeImmutable $end, public array $definition, public array $participants)
    {
    }

    public function includedIds(): array
    {
        return array_keys(array_filter($this->participants, static fn (string $status): bool => $status === 'INCLUDED'));
    }
}
