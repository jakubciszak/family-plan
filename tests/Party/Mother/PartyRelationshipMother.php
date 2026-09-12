<?php

declare(strict_types=1);

namespace App\Tests\Party\Mother;

use App\Party\Domain\Entity\Party;
use App\Party\Domain\Entity\PartyRelationship;
use App\Party\Domain\Entity\PartyRole;
use App\Party\Domain\Repository\PartyRoleRepositoryInterface;
use App\Party\Domain\ValueObject\PartyRelationshipType;
use App\Party\Domain\ValueObject\PartyRoleType;
use App\Shared\Domain\ValueObject\Uuid;

final class PartyRelationshipMother
{
    public static function create(
        Uuid $id,
        Party $from,
        Party $to,
        PartyRelationshipType $type,
        ?PartyRoleRepositoryInterface $roles = null
    ): PartyRelationship {
        return PartyRelationship::create(
            $id,
            self::role($from, $type->fromRoleType(), $roles),
            self::role($to, $type->toRoleType(), $roles),
            $type
        );
    }

    public static function role(
        Party $party,
        PartyRoleType $type,
        ?PartyRoleRepositoryInterface $roles = null
    ): PartyRole {
        $existing = $roles?->findByPartyAndType($party->id(), $type);

        if ($existing !== null) {
            return $existing;
        }

        $role = PartyRole::start(Uuid::generate(), $party, $type);
        $roles?->save($role);

        return $role;
    }
}
