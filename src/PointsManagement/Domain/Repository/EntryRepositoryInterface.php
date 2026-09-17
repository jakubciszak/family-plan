<?php

declare(strict_types=1);

namespace App\PointsManagement\Domain\Repository;

use App\PointsManagement\Domain\Entity\Entry;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

interface EntryRepositoryInterface
{
    public function save(Entry $entry): void;

    /**
     * Points booked for a user between two moments, limited to the given accounts.
     *
     * @param AccountKind[] $kinds
     */
    public function sumBetween(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds): int;

    /**
     * @param AccountKind[] $kinds
     * @return array<string, int> points per day, keyed by Y-m-d
     */
    public function perDayBetween(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds): array;

    public function find(Uuid $id): ?Entry;

    /**
     * @param AccountKind[] $kinds
     * @return Entry[] what was booked, oldest first
     */
    public function between(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds): array;

    public function existsFor(Uuid $accountId, Uuid $reference, string $periodKey): bool;
}
