<?php

declare(strict_types=1);

namespace App\Allowance\Infrastructure\Persistence;

use App\Allowance\Domain\Entity\WeekClosure;
use App\Allowance\Domain\Repository\WeekClosureRepositoryInterface;
use App\Allowance\Domain\ValueObject\WeekStart;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineWeekClosureRepository implements WeekClosureRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(Uuid $userId, WeekStart $week): ?WeekClosure
    {
        return $this->entityManager->getRepository(WeekClosure::class)->findOneBy([
            'userId' => $userId,
            'weekStart' => $week->monday(),
        ]);
    }

    public function ofUserBetween(Uuid $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(WeekClosure::class, 'c')
            ->where('c.userId = :userId')
            ->andWhere('c.weekStart >= :from')
            ->andWhere('c.weekStart < :to')
            ->setParameter('userId', $userId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.weekStart', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(WeekClosure $closure): void
    {
        $this->entityManager->persist($closure);
        $this->entityManager->flush();
    }

    public function remove(WeekClosure $closure): void
    {
        $this->entityManager->remove($closure);
        $this->entityManager->flush();
    }
}
