<?php

declare(strict_types=1);

namespace App\Allowance\Infrastructure\Persistence;

use App\Allowance\Domain\Entity\MoneyEntry;
use App\Allowance\Domain\Repository\MoneyEntryRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMoneyEntryRepository implements MoneyEntryRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(MoneyEntry $entry): void
    {
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    public function ofTransactions(array $transactionIds): array
    {
        if ($transactionIds === []) {
            return [];
        }

        return $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(MoneyEntry::class, 'e')
            ->where('e.transactionId IN (:ids)')
            ->setParameter('ids', $transactionIds)
            ->getQuery()
            ->getResult();
    }
}
