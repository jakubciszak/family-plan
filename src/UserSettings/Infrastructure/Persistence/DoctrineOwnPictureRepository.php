<?php

declare(strict_types=1);

namespace App\UserSettings\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\Entity\OwnPicture;
use App\UserSettings\Domain\Repository\OwnPictureRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineOwnPictureRepository implements OwnPictureRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(Uuid $id): ?OwnPicture
    {
        return $this->entityManager->getRepository(OwnPicture::class)->find($id->value());
    }

    public function ofUser(Uuid $userId, ?string $purpose = null): array
    {
        $criteria = ['userId' => $userId];

        if ($purpose !== null) {
            $criteria['purpose'] = $purpose;
        }

        return $this->entityManager->getRepository(OwnPicture::class)->findBy($criteria, ['createdAt' => 'DESC']);
    }

    public function save(OwnPicture $picture): void
    {
        $this->entityManager->persist($picture);
        $this->entityManager->flush();
    }

    public function remove(OwnPicture $picture): void
    {
        $this->entityManager->remove($picture);
        $this->entityManager->flush();
    }
}
