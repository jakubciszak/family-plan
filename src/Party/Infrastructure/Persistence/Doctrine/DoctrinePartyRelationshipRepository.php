<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\Doctrine;

use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Repository\PartyRelationshipRepositoryInterface;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine Party Relationship Repository
 *
 * Production implementation using Doctrine ORM
 */
class DoctrinePartyRelationshipRepository implements PartyRelationshipRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(PartyRelationship $relationship): void
    {
        $this->entityManager->persist($relationship);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?PartyRelationship
    {
        return $this->entityManager->find(PartyRelationship::class, $id);
    }

    public function findByFromParty(Uuid $fromPartyId): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('pr')
            ->from(PartyRelationship::class, 'pr')
            ->where('pr.from = :fromPartyId')
            ->setParameter('fromPartyId', $fromPartyId)
            ->getQuery()
            ->getResult();
    }

    public function findByToParty(Uuid $toPartyId): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('pr')
            ->from(PartyRelationship::class, 'pr')
            ->where('pr.to = :toPartyId')
            ->setParameter('toPartyId', $toPartyId)
            ->getQuery()
            ->getResult();
    }

    public function findActiveRelationships(Uuid $fromPartyId, Uuid $toPartyId): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('pr')
            ->from(PartyRelationship::class, 'pr')
            ->where('pr.from = :fromPartyId')
            ->andWhere('pr.to = :toPartyId')
            ->andWhere('pr.endedAt IS NULL')
            ->setParameter('fromPartyId', $fromPartyId)
            ->setParameter('toPartyId', $toPartyId)
            ->getQuery()
            ->getResult();
    }

    public function findByFromPartyAndType(Uuid $fromPartyId, PartyRelationshipType $type): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('pr')
            ->from(PartyRelationship::class, 'pr')
            ->where('pr.from = :fromPartyId')
            ->andWhere('pr.type = :type')
            ->setParameter('fromPartyId', $fromPartyId)
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }

    public function isPartyAdminOf(Uuid $fromPartyId, Uuid $toPartyId): bool
    {
        $count = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(pr.id)')
            ->from(PartyRelationship::class, 'pr')
            ->where('pr.from = :fromPartyId')
            ->andWhere('pr.to = :toPartyId')
            ->andWhere('pr.type = :adminType')
            ->andWhere('pr.endedAt IS NULL')
            ->setParameter('fromPartyId', $fromPartyId)
            ->setParameter('toPartyId', $toPartyId)
            ->setParameter('adminType', PartyRelationshipType::adminOf())
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
