<?php

declare(strict_types=1);

namespace App\PointsManagement\Infrastructure\Persistence;

use App\PointsManagement\Domain\Entity\Account;
use App\PointsManagement\Domain\Entity\Entry;
use App\PointsManagement\Domain\Repository\AccountRepositoryInterface;
use App\PointsManagement\Domain\Repository\EntryRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class InMemoryEntryRepository implements EntryRepositoryInterface
{
    /** @var Entry[] */
    private array $entries = [];

    public function __construct(private readonly AccountRepositoryInterface $accounts)
    {
    }

    public function save(Entry $entry): void
    {
        $this->entries[$entry->id()->value()] = $entry;
    }

    public function sumBetween(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds): int
    {
        return array_sum(array_map(
            static fn (Entry $entry) => $entry->amount(),
            $this->matching($userId, $from, $to, $kinds)
        ));
    }

    public function perDayBetween(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds): array
    {
        $perDay = [];

        foreach ($this->matching($userId, $from, $to, $kinds) as $entry) {
            $day = $entry->bookedAt()->format('Y-m-d');
            $perDay[$day] = ($perDay[$day] ?? 0) + $entry->amount();
        }

        return $perDay;
    }

    public function between(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds): array
    {
        $entries = array_values($this->matching($userId, $from, $to, $kinds));

        usort($entries, static fn (Entry $a, Entry $b) => $a->bookedAt() <=> $b->bookedAt());

        return $entries;
    }

    public function existsFor(Uuid $accountId, Uuid $reference, string $periodKey): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry->belongsTo($accountId)
                && $entry->reference() === $reference->value()
                && $entry->periodKey() === $periodKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Entry[]
     */
    private function matching(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds): array
    {
        $wanted = [];

        foreach ($this->accounts->ofUser($userId) as $account) {
            if (in_array($account->kind(), $kinds, true)) {
                $wanted[] = $account->id()->value();
            }
        }

        return array_filter($this->entries, static fn (Entry $entry) =>
            in_array($entry->accountId()->value(), $wanted, true)
            && $entry->bookedAt() >= $from
            && $entry->bookedAt() < $to);
    }
}
