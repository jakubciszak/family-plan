<?php

declare(strict_types=1);

namespace App\PointsManagement\Infrastructure\Persistence;

use App\PointsManagement\Domain\Entity\Account;
use App\PointsManagement\Domain\Repository\AccountRepositoryInterface;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAccountRepository implements AccountRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(Uuid $userId, AccountKind $kind, ?Uuid $teamId = null): ?Account
    {
        return $this->entityManager->getRepository(Account::class)->findOneBy([
            'userId' => $userId, 'teamId' => $teamId?->value() ?? '',
            'kind' => $kind,
        ]);
    }

    public function ofUser(Uuid $userId, ?Uuid $teamId = null): array
    {
        return $this->entityManager->getRepository(Account::class)->findBy(['userId' => $userId, 'teamId' => $teamId?->value() ?? '']);
    }

    public function save(Account $account): void
    {
        $this->entityManager->persist($account);
        $this->entityManager->flush();
    }
}
