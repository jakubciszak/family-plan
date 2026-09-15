<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\Entity\OwnPicture;

interface OwnPictureRepositoryInterface
{
    public function find(Uuid $id): ?OwnPicture;

    /**
     * @return OwnPicture[]
     */
    public function ofUser(Uuid $userId, ?string $purpose = null): array;

    public function save(OwnPicture $picture): void;

    public function remove(OwnPicture $picture): void;
}
