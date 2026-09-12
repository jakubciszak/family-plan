<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\Doctrine;

use App\Party\Domain\Entity\PartyRole;
use App\Party\Domain\Repository\PartyRoleRepositoryInterface;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

class DoctrinePartyRoleRepository implements PartyRoleRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(PartyRole $role): void
    {
        $this->entityManager->persist($role);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?PartyRole
    {
        return $this->entityManager->find(PartyRole::class, $id);
    }

    public function findByParty(Uuid $partyId): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(PartyRole::class, 'r')
            ->where('r.party = :partyId')
            ->setParameter('partyId', $partyId)
            ->getQuery()
            ->getResult();
    }

    public function findByPartyAndType(Uuid $partyId, PartyRoleType $type): ?PartyRole
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(PartyRole::class, 'r')
            ->where('r.party = :partyId')
            ->andWhere('r.type = :type')
            ->setParameter('partyId', $partyId)
            ->setParameter('type', $type)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByType(PartyRoleType $type): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('r')
            ->from(PartyRole::class, 'r')
            ->where('r.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }
}
