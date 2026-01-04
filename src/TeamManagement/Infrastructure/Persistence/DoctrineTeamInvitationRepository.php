<?php

declare(strict_types=1);

namespace App\TeamManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\TeamInvitation;
use App\TeamManagement\Domain\Repository\TeamInvitationRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTeamInvitationRepository implements TeamInvitationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(TeamInvitation $invitation): void
    {
        $this->entityManager->persist($invitation);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?TeamInvitation
    {
        return $this->entityManager->find(TeamInvitation::class, $id->value());
    }

    public function findByToken(string $token): ?TeamInvitation
    {
        return $this->entityManager->getRepository(TeamInvitation::class)
            ->createQueryBuilder('ti')
            ->where('ti.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByTeamId(Uuid $teamId): array
    {
        return $this->entityManager->getRepository(TeamInvitation::class)
            ->createQueryBuilder('ti')
            ->where('ti.teamId = :teamId')
            ->setParameter('teamId', $teamId->value())
            ->orderBy('ti.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByEmail(Email $email): array
    {
        return $this->entityManager->getRepository(TeamInvitation::class)
            ->createQueryBuilder('ti')
            ->where('ti.email = :email')
            ->setParameter('email', $email->value())
            ->orderBy('ti.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingByEmail(Email $email): array
    {
        return $this->entityManager->getRepository(TeamInvitation::class)
            ->createQueryBuilder('ti')
            ->where('ti.email = :email')
            ->andWhere('ti.status = :status')
            ->setParameter('email', $email->value())
            ->setParameter('status', 'pending')
            ->orderBy('ti.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function remove(TeamInvitation $invitation): void
    {
        $this->entityManager->remove($invitation);
        $this->entityManager->flush();
    }
}
