<?php

declare(strict_types=1);

namespace App\Party\Domain\Repository;

use App\Party\Domain\Entity\Responsibility;
use App\Shared\Domain\ValueObject\Uuid;

interface ResponsibilityRepositoryInterface
{
    public function save(Responsibility $responsibility): void;

    public function findById(Uuid $id): ?Responsibility;

    /**
     * @return Responsibility[]
     */
    public function findByRole(Uuid $partyRoleId): array;
}
