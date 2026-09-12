<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\InMemory;

use App\Party\Domain\Entity\Responsibility;
use App\Party\Domain\Repository\ResponsibilityRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;

class InMemoryResponsibilityRepository implements ResponsibilityRepositoryInterface
{
    /**
     * @var array<string, Responsibility>
     */
    private array $responsibilities = [];

    public function save(Responsibility $responsibility): void
    {
        $this->responsibilities[$responsibility->id()->value()] = $responsibility;
    }

    public function findById(Uuid $id): ?Responsibility
    {
        return $this->responsibilities[$id->value()] ?? null;
    }

    public function findByRole(Uuid $partyRoleId): array
    {
        return array_values(array_filter(
            $this->responsibilities,
            fn (Responsibility $responsibility) => $responsibility->partyRole()->id()->equals($partyRoleId)
        ));
    }

    public function clear(): void
    {
        $this->responsibilities = [];
    }
}
