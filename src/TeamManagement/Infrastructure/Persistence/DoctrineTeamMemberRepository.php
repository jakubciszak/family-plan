<?php

declare(strict_types=1);

namespace App\TeamManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\TeamMember;
use App\TeamManagement\Domain\Repository\TeamMemberRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTeamMemberRepository implements TeamMemberRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(TeamMember $member): void
    {
        $this->entityManager->persist($member);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?TeamMember
    {
        return $this->entityManager->find(TeamMember::class, $id->value());
    }

    public function findByTeamIdAndUserId(Uuid $teamId, Uuid $userId): ?TeamMember
    {
        return $this->entityManager->getRepository(TeamMember::class)
            ->createQueryBuilder('tm')
            ->where('tm.teamId = :teamId')
            ->andWhere('tm.userId = :userId')
            ->setParameter('teamId', $teamId->value())
            ->setParameter('userId', $userId->value())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByTeamId(Uuid $teamId): array
    {
        return $this->entityManager->getRepository(TeamMember::class)
            ->createQueryBuilder('tm')
            ->where('tm.teamId = :teamId')
            ->setParameter('teamId', $teamId->value())
            ->getQuery()
            ->getResult();
    }

    public function findByUserId(Uuid $userId): array
    {
        return $this->entityManager->getRepository(TeamMember::class)
            ->createQueryBuilder('tm')
            ->where('tm.userId = :userId')
            ->setParameter('userId', $userId->value())
            ->getQuery()
            ->getResult();
    }

    public function remove(TeamMember $member): void
    {
        $this->entityManager->remove($member);
        $this->entityManager->flush();
    }

    public function isUserMemberOfTeam(Uuid $userId, Uuid $teamId): bool
    {
        return $this->findByTeamIdAndUserId($teamId, $userId) !== null;
    }

    public function isUserAdminOfTeam(Uuid $userId, Uuid $teamId): bool
    {
        $member = $this->findByTeamIdAndUserId($teamId, $userId);
        return $member !== null && $member->isAdmin();
    }
}
