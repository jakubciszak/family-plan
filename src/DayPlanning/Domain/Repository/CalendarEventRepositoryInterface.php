<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\Repository;

use App\DayPlanning\Domain\Entity\CalendarEvent;
use App\Shared\Domain\ValueObject\Uuid;

interface CalendarEventRepositoryInterface
{
    public function find(Uuid $id): ?CalendarEvent;
    public function forPeople(array $personIds): array;
    public function findByTeam(string $teamId): array;
    public function save(CalendarEvent $event): void;
    public function transactional(callable $operation): mixed;
    public function idempotentResult(string $ownerId, string $key, string $hash): ?array;
    public function rememberResult(string $ownerId, string $key, string $hash, array $result): void;
}
