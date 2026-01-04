<?php

declare(strict_types=1);

namespace App\TeamManagement\Infrastructure\Persistence\Doctrine\Type;

use App\TeamManagement\Domain\ValueObject\TeamName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TeamNameType extends Type
{
    private const TYPE_NAME = 'team_name';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 255]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TeamName
    {
        if ($value === null || $value instanceof TeamName) {
            return $value;
        }

        return TeamName::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TeamName) {
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
