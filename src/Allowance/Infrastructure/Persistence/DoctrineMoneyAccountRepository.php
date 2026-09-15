<?php

declare(strict_types=1);

namespace App\Allowance\Infrastructure\Persistence;

use App\Allowance\Domain\Entity\MoneyAccount;
use App\Allowance\Domain\Repository\MoneyAccountRepositoryInterface;
use App\Allowance\Domain\ValueObject\AccountKind;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMoneyAccountRepository implements MoneyAccountRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(Uuid $userId, AccountKind $kind, ?Uuid $reference = null): ?MoneyAccount
    {
        return $this->entityManager->getRepository(MoneyAccount::class)->findOneBy([
            'userId' => $userId,
            'kind' => $kind,
            'reference' => $reference?->value() ?? '',
        ]);
    }

    public function ofUser(Uuid $userId): array
    {
        return $this->entityManager->getRepository(MoneyAccount::class)->findBy(['userId' => $userId]);
    }

    public function save(MoneyAccount $account): void
    {
        $this->entityManager->persist($account);
        $this->entityManager->flush();
    }
}
