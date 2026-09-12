<?php

declare(strict_types=1);

namespace App\Party\Domain\Repository;

use App\Party\Domain\Entity\Signature;
use App\Shared\Domain\ValueObject\Uuid;

interface SignatureRepositoryInterface
{
    public function save(Signature $signature): void;

    public function findById(Uuid $id): ?Signature;

    /**
     * @return Signature[]
     */
    public function findBySubject(Uuid $subjectId): array;

    /**
     * @return Signature[]
     */
    public function findBySignatory(Uuid $partyRoleId): array;
}
