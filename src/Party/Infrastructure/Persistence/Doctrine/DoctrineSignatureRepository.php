<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\Doctrine;

use App\Party\Domain\Entity\Signature;
use App\Party\Domain\Repository\SignatureRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineSignatureRepository implements SignatureRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(Signature $signature): void
    {
        $this->entityManager->persist($signature);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?Signature
    {
        return $this->entityManager->find(Signature::class, $id);
    }

    public function findBySubject(Uuid $subjectId): array
    {
        return $this->query()
            ->where('s.subjectId = :subjectId')
            ->setParameter('subjectId', $subjectId)
            ->orderBy('s.signedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySignatory(Uuid $partyRoleId): array
    {
        return $this->query()
            ->where('s.signatory = :roleId')
            ->setParameter('roleId', $partyRoleId)
            ->orderBy('s.signedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function query(): \Doctrine\ORM\QueryBuilder
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(Signature::class, 's');
    }
}
