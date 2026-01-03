<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\Repository;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\Entity\UserSettings;

interface UserSettingsRepositoryInterface
{
    public function save(UserSettings $settings): void;

    public function findByUserId(Uuid $userId): ?UserSettings;

    public function delete(UserSettings $settings): void;
}
