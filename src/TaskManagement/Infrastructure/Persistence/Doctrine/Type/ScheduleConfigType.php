<?php

declare(strict_types=1);

namespace App\TaskManagement\Infrastructure\Persistence\Doctrine\Type;

use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ScheduleConfigType extends Type
{
    private const TYPE_NAME = 'schedule_config';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 500]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ScheduleConfig
    {
        if ($value === null || $value instanceof ScheduleConfig) {
            return $value;
        }

        $data = json_decode($value, true);
        return ScheduleConfig::fromArray($data);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ScheduleConfig) {
            return json_encode($value->toArray());
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
