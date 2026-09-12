<?php

declare(strict_types=1);

namespace App\Party\Infrastructure\Persistence\InMemory;

use App\Party\Domain\Entity\PartyRole;
use App\Party\Domain\Repository\PartyRoleRepositoryInterface;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Shared\Domain\ValueObject\Uuid;

class InMemoryPartyRoleRepository implements PartyRoleRepositoryInterface
{
    /**
     * @var array<string, PartyRole>
     */
    private array $roles = [];

    public function save(PartyRole $role): void
    {
        $this->roles[$role->id()->value()] = $role;
    }

    public function findById(Uuid $id): ?PartyRole
    {
        return $this->roles[$id->value()] ?? null;
    }

    public function findByParty(Uuid $partyId): array
    {
        return array_values(array_filter(
            $this->roles,
            fn (PartyRole $role) => $role->partyId()->equals($partyId)
        ));
    }

    public function findByPartyAndType(Uuid $partyId, PartyRoleType $type): ?PartyRole
    {
        foreach ($this->roles as $role) {
            if ($role->partyId()->equals($partyId) && $role->type()->equals($type)) {
                return $role;
            }
        }

        return null;
    }

    public function findByType(PartyRoleType $type): array
    {
        return array_values(array_filter(
            $this->roles,
            fn (PartyRole $role) => $role->type()->equals($type)
        ));
    }

    public function clear(): void
    {
        $this->roles = [];
    }
}
