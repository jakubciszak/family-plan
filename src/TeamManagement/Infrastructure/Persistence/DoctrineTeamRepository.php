<?php

declare(strict_types=1);

namespace App\TeamManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Entity\Team;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTeamRepository implements TeamRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(Team $team): void
    {
        $this->entityManager->persist($team);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?Team
    {
        return $this->entityManager->find(Team::class, $id->value());
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(Team::class)->findAll();
    }

    public function remove(Team $team): void
    {
        $this->entityManager->remove($team);
        $this->entityManager->flush();
    }
}
