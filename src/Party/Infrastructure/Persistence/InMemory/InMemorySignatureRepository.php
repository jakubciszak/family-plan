<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\InMemory;

use App\Party\Domain\Entity\Signature;
use App\Party\Domain\Repository\SignatureRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;

class InMemorySignatureRepository implements SignatureRepositoryInterface
{
    /**
     * @var array<string, Signature>
     */
    private array $signatures = [];

    public function save(Signature $signature): void
    {
        $this->signatures[$signature->id()->value()] = $signature;
    }

    public function findById(Uuid $id): ?Signature
    {
        return $this->signatures[$id->value()] ?? null;
    }

    public function findBySubject(Uuid $subjectId): array
    {
        return array_values(array_filter(
            $this->signatures,
            fn (Signature $signature) => $signature->subjectId()->equals($subjectId)
        ));
    }

    public function findBySignatory(Uuid $partyRoleId): array
    {
        return array_values(array_filter(
            $this->signatures,
            fn (Signature $signature) => $signature->signatory()->id()->equals($partyRoleId)
        ));
    }

    public function clear(): void
    {
        $this->signatures = [];
    }
}
