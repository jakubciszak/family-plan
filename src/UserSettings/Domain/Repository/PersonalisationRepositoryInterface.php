<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\Entity\Personalisation;

interface PersonalisationRepositoryInterface
{
    public function ofUser(Uuid $userId): ?Personalisation;

    /**
     * @param Uuid[] $userIds
     * @return Personalisation[]
     */
    public function ofUsers(array $userIds): array;

    public function save(Personalisation $personalisation): void;
}
