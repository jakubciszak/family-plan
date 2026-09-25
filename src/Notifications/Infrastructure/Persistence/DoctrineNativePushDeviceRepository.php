<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence;

use App\Notifications\Domain\Entity\NativePushDevice;
use App\Notifications\Domain\Repository\NativePushDeviceRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineNativePushDeviceRepository implements NativePushDeviceRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(NativePushDevice $device): void
    {
        $this->entityManager->persist($device);
        $this->entityManager->flush();
    }

    public function delete(NativePushDevice $device): void
    {
        $this->entityManager->remove($device);
        $this->entityManager->flush();
    }

    public function findByToken(string $token): ?NativePushDevice
    {
        return $this->entityManager->getRepository(NativePushDevice::class)->findOneBy(['token' => $token]);
    }

    public function findForUser(Uuid $userId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(NativePushDevice::class, 'd')
            ->where('d.userId = :userId')
            ->orderBy('d.createdAt', 'ASC')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    public function countForUser(Uuid $userId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(NativePushDevice::class, 'd')
            ->where('d.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
