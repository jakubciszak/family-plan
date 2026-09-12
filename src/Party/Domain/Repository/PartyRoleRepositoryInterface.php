<?php

declare(strict_types=1);

namespace App\Party\Domain\Repository;

use App\Party\Domain\Entity\PartyRole;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Shared\Domain\ValueObject\Uuid;

interface PartyRoleRepositoryInterface
{
    public function save(PartyRole $role): void;

    public function findById(Uuid $id): ?PartyRole;

    /**
     * @return PartyRole[]
     */
    public function findByParty(Uuid $partyId): array;

    public function findByPartyAndType(Uuid $partyId, PartyRoleType $type): ?PartyRole;

    /**
     * @return PartyRole[]
     */
    public function findByType(PartyRoleType $type): array;
}
