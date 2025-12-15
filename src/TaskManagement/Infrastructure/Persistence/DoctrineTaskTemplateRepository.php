<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineTaskTemplateRepository implements TaskTemplateRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(TaskTemplate $taskTemplate): void
    {
        $this->entityManager->persist($taskTemplate);
        $this->entityManager->flush();
    }

    public function findById(Uuid $id): ?TaskTemplate
    {
        return $this->entityManager->getRepository(TaskTemplate::class)->find($id);
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(TaskTemplate::class)->findAll();
    }

    public function findByAssignedUser(Uuid $userId): array
    {
        return $this->entityManager->getRepository(TaskTemplate::class)->findBy([
            'assignedUserId' => $userId
        ]);
    }

    public function findActive(): array
    {
        return $this->entityManager->getRepository(TaskTemplate::class)->findBy([
            'isActive' => true
        ]);
    }

    public function delete(TaskTemplate $taskTemplate): void
    {
        $this->entityManager->remove($taskTemplate);
        $this->entityManager->flush();
    }
}
