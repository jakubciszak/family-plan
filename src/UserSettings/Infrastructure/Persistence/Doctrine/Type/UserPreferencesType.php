<?php

declare(strict_types=1);

namespace App\UserSettings\Infrastructure\Persistence\Doctrine\Type;

use App\UserSettings\Domain\ValueObject\UserPreferences;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class UserPreferencesType extends Type
{
    private const NAME = 'user_preferences';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?UserPreferences
    {
        if ($value === null) {
            return null;
        }

        $data = json_decode($value, true);
        return UserPreferences::fromArray($data);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof UserPreferences) {
            throw new \InvalidArgumentException('Expected UserPreferences instance');
        }

        return json_encode($value->toArray());
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
