<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\Doctrine;

use App\Party\Domain\Entity\Organization;
use App\Party\Domain\Entity\Party;
use App\Party\Domain\Entity\Person;
use App\Party\Domain\Repository\PartyRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine Party Repository
 *
 * Production implementation using Doctrine ORM
 */
class DoctrinePartyRepository implements PartyRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(Party $party): void
    {
        $this->entityManager->persist($party);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?Party
    {
        return $this->entityManager->find(Party::class, $id);
    }

    public function findAll(): array
    {
        return $this->entityManager
            ->getRepository(Party::class)
            ->findAll();
    }

    public function findPersons(): array
    {
        return $this->entityManager
            ->getRepository(Person::class)
            ->findAll();
    }

    public function findOrganizations(): array
    {
        return $this->entityManager
            ->getRepository(Organization::class)
            ->findAll();
    }
}
