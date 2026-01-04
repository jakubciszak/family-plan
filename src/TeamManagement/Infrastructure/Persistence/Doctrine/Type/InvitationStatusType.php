<?php

declare(strict_types=1);

namespace App\TeamManagement\Infrastructure\Persistence\Doctrine\Type;

use App\TeamManagement\Domain\ValueObject\InvitationStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class InvitationStatusType extends Type
{
    private const TYPE_NAME = 'invitation_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?InvitationStatus
    {
        if ($value === null || $value instanceof InvitationStatus) {
            return $value;
        }

        return InvitationStatus::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof InvitationStatus) {
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
