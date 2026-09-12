<?php

declare(strict_types=1);

namespace App\Party\Domain\Service;

use App\Party\Domain\ValueObject\PartyRoleType;
use App\Party\Domain\ValueObject\ResponsibilityType;

final class RoleResponsibilities
{
    private const ALLOCATION = [
        'TEAM_ADMIN' => [
            ResponsibilityType::DEFINE_TASK_TYPE,
            ResponsibilityType::TAKE_TASK,
            ResponsibilityType::ASSIGN_TASK,
            ResponsibilityType::COMPLETE_TASK,
            ResponsibilityType::APPROVE_TASK,
        ],
        'TEAM_MEMBER' => [
            ResponsibilityType::TAKE_TASK,
            ResponsibilityType::COMPLETE_TASK,
        ],
        'TEAM' => [],
    ];

    /**
     * @return ResponsibilityType[]
     */
    public static function of(PartyRoleType $roleType): array
    {
        return array_map(
            static fn (string $type) => ResponsibilityType::fromString($type),
            self::ALLOCATION[$roleType->value()]
        );
    }
}
