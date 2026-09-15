<?php

declare(strict_types=1);

namespace App\UserSettings\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\Entity\Personalisation;
use App\UserSettings\Domain\Repository\PersonalisationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePersonalisationRepository implements PersonalisationRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function ofUser(Uuid $userId): ?Personalisation
    {
        return $this->entityManager->getRepository(Personalisation::class)->findOneBy(['userId' => $userId]);
    }

    public function ofUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Personalisation::class, 'p')
            ->where('p.userId IN (:ids)')
            ->setParameter('ids', array_map(static fn (Uuid $id) => $id->value(), $userIds))
            ->getQuery()
            ->getResult();
    }

    public function save(Personalisation $personalisation): void
    {
        $this->entityManager->persist($personalisation);
        $this->entityManager->flush();
    }
}
