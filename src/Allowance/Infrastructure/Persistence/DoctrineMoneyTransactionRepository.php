<?php

declare(strict_types=1);

namespace App\Allowance\Infrastructure\Persistence;

use App\Allowance\Domain\Entity\MoneyTransaction;
use App\Allowance\Domain\Repository\MoneyTransactionRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMoneyTransactionRepository implements MoneyTransactionRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(MoneyTransaction $transaction): void
    {
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();
    }

    public function find(Uuid $id): ?MoneyTransaction
    {
        return $this->entityManager->getRepository(MoneyTransaction::class)->find($id->value());
    }

    public function ofUser(Uuid $userId, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null, int $limit = 100): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(MoneyTransaction::class, 't')
            ->where('t.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.bookedAt', 'DESC')
            ->setMaxResults($limit);

        if ($from !== null) {
            $query->andWhere('t.bookedAt >= :from')->setParameter('from', $from);
        }

        if ($to !== null) {
            $query->andWhere('t.bookedAt < :to')->setParameter('to', $to);
        }

        return $query->getQuery()->getResult();
    }
}
