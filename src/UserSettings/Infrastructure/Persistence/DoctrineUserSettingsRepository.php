<?php

declare(strict_types=1);

namespace App\UserSettings\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\Entity\UserSettings;
use App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserSettingsRepository implements UserSettingsRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(UserSettings $settings): void
    {
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }

    public function findByUserId(Uuid $userId): ?UserSettings
    {
        return $this->entityManager
            ->getRepository(UserSettings::class)
            ->findOneBy(['userId' => $userId]);
    }

    public function delete(UserSettings $settings): void
    {
        $this->entityManager->remove($settings);
        $this->entityManager->flush();
    }
}
