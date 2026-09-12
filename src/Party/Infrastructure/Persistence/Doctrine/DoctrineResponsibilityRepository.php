<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\Doctrine;

use App\Party\Domain\Entity\Responsibility;
use App\Party\Domain\Repository\ResponsibilityRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineResponsibilityRepository implements ResponsibilityRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(Responsibility $responsibility): void
    {
        $this->entityManager->persist($responsibility);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?Responsibility
    {
        return $this->entityManager->find(Responsibility::class, $id);
    }

    public function findByRole(Uuid $partyRoleId): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(Responsibility::class, 'r')
            ->where('r.partyRole = :roleId')
            ->setParameter('roleId', $partyRoleId)
            ->getQuery()
            ->getResult();
    }
}
