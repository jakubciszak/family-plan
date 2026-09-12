<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\Doctrine;

use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Repository\PartyRelationshipRepositoryInterface;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

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

    public function findByFromRole(Uuid $fromPartyRoleId): array
    {
        return $this->query()
            ->where('pr.from = :fromRoleId')
            ->setParameter('fromRoleId', $fromPartyRoleId)
            ->getQuery()
            ->getResult();
    }

    public function findByToRole(Uuid $toPartyRoleId): array
    {
        return $this->query()
            ->where('pr.to = :toRoleId')
            ->setParameter('toRoleId', $toPartyRoleId)
            ->getQuery()
            ->getResult();
    }

    public function findByFromParty(Uuid $fromPartyId): array
    {
        return $this->query()
            ->join('pr.from', 'fromRole')
            ->where('fromRole.party = :fromPartyId')
            ->setParameter('fromPartyId', $fromPartyId)
            ->getQuery()
            ->getResult();
    }

    public function findByToParty(Uuid $toPartyId): array
    {
        return $this->query()
            ->join('pr.to', 'toRole')
            ->where('toRole.party = :toPartyId')
            ->setParameter('toPartyId', $toPartyId)
            ->getQuery()
            ->getResult();
    }

    public function findActiveRelationships(Uuid $fromPartyId, Uuid $toPartyId): array
    {
        return $this->betweenParties($fromPartyId, $toPartyId)
            ->andWhere('pr.endedAt IS NULL')
            ->getQuery()
            ->getResult();
    }

    public function findByFromPartyAndType(Uuid $fromPartyId, PartyRelationshipType $type): array
    {
        return $this->query()
            ->join('pr.from', 'fromRole')
            ->where('fromRole.party = :fromPartyId')
            ->andWhere('pr.type = :type')
            ->setParameter('fromPartyId', $fromPartyId)
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }

    public function isPartyAdminOf(Uuid $fromPartyId, Uuid $toPartyId): bool
    {
        $count = $this->betweenParties($fromPartyId, $toPartyId)
            ->select('COUNT(pr.id)')
            ->andWhere('pr.type = :adminType')
            ->andWhere('pr.endedAt IS NULL')
            ->setParameter('adminType', PartyRelationshipType::adminOf())
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    private function betweenParties(Uuid $fromPartyId, Uuid $toPartyId): QueryBuilder
    {
        return $this->query()
            ->join('pr.from', 'fromRole')
            ->join('pr.to', 'toRole')
            ->where('fromRole.party = :fromPartyId')
            ->andWhere('toRole.party = :toPartyId')
            ->setParameter('fromPartyId', $fromPartyId)
            ->setParameter('toPartyId', $toPartyId);
    }

    private function query(): QueryBuilder
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('pr')
            ->from(PartyRelationship::class, 'pr');
    }
}
