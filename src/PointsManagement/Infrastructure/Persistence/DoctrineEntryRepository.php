<?php

declare(strict_types=1);

namespace App\PointsManagement\Infrastructure\Persistence;

use App\PointsManagement\Domain\Entity\Account;
use App\PointsManagement\Domain\Entity\Entry;
use App\PointsManagement\Domain\Repository\EntryRepositoryInterface;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEntryRepository implements EntryRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Entry $entry): void
    {
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    public function find(Uuid $id): ?Entry
    {
        return $this->entityManager->find(Entry::class, $id);
    }

    public function sumBetween(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds): int
    {
        if ($kinds === []) {
            return 0;
        }

        $total = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(e.amount), 0)')
            ->from(Entry::class, 'e')
            ->join(Account::class, 'a', 'WITH', 'a.id = e.accountId')
            ->where('a.userId = :userId')
            ->andWhere('a.kind IN (:kinds)')
            ->andWhere('e.bookedAt >= :from')
            ->andWhere('e.bookedAt < :to')
            ->setParameter('userId', $userId)
            ->setParameter('kinds', array_map(static fn (AccountKind $kind) => $kind->value, $kinds))
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $total;
    }

    public function perDayBetween(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds): array
    {
        if ($kinds === []) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('e.bookedAt AS bookedAt', 'e.amount AS amount')
            ->from(Entry::class, 'e')
            ->join(Account::class, 'a', 'WITH', 'a.id = e.accountId')
            ->where('a.userId = :userId')
            ->andWhere('a.kind IN (:kinds)')
            ->andWhere('e.bookedAt >= :from')
            ->andWhere('e.bookedAt < :to')
            ->setParameter('userId', $userId)
            ->setParameter('kinds', array_map(static fn (AccountKind $kind) => $kind->value, $kinds))
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getArrayResult();

        $perDay = [];

        foreach ($rows as $row) {
            $day = $row['bookedAt']->format('Y-m-d');
            $perDay[$day] = ($perDay[$day] ?? 0) + (int) $row['amount'];
        }

        return $perDay;
    }

    public function between(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to, array $kinds): array
    {
        if ($kinds === []) {
            return [];
        }

        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(Entry::class, 'e')
            ->join(Account::class, 'a', 'WITH', 'a.id = e.accountId')
            ->where('a.userId = :userId')
            ->andWhere('a.kind IN (:kinds)')
            ->andWhere('e.bookedAt >= :from')
            ->andWhere('e.bookedAt < :to')
            ->orderBy('e.bookedAt', 'ASC')
            ->setParameter('userId', $userId)
            ->setParameter('kinds', array_map(static fn (AccountKind $kind) => $kind->value, $kinds))
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    public function existsFor(Uuid $accountId, Uuid $reference, string $periodKey): bool
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(Entry::class, 'e')
            ->where('e.accountId = :accountId')
            ->andWhere('e.reference = :reference')
            ->andWhere('e.periodKey = :periodKey')
            ->setParameter('accountId', $accountId)
            ->setParameter('reference', $reference->value())
            ->setParameter('periodKey', $periodKey)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
