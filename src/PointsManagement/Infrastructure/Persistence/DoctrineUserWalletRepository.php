<?php

declare(strict_types=1);

namespace App\PointsManagement\Infrastructure\Persistence;

use App\PointsManagement\Domain\Entity\UserWallet;
use App\PointsManagement\Domain\Repository\UserWalletRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

/**
 * Doctrine implementation of UserWalletRepository
 */
final readonly class DoctrineUserWalletRepository implements UserWalletRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->repository = $entityManager->getRepository(UserWallet::class);
    }

    public function save(UserWallet $wallet): void
    {
        $this->entityManager->persist($wallet);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?UserWallet
    {
        return $this->repository->find($id);
    }

    public function findByUserId(Uuid $userId): ?UserWallet
    {
        return $this->repository->findOneBy(['userId' => $userId]);
    }

    public function delete(UserWallet $wallet): void
    {
        $this->entityManager->remove($wallet);
        $this->entityManager->flush();
    }
}
