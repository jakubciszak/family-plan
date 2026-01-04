<?php

declare(strict_types=1);

namespace App\TeamManagement\Infrastructure\Persistence\Doctrine\Type;

use App\TeamManagement\Domain\ValueObject\TeamRole;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TeamRoleType extends Type
{
    private const TYPE_NAME = 'team_role';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TeamRole
    {
        if ($value === null || $value instanceof TeamRole) {
            return $value;
        }

        return TeamRole::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TeamRole) {
            return $value->value();
        }

        return $value;
    }

    public function getName(): string
    {
        return self::TYPE_NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
