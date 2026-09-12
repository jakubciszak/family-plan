<?php

declare(strict_types=1);

namespace App\UserSettings\Infrastructure\Persistence;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\Entity\UserSettings;
use App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface;

class InMemoryUserSettingsRepository implements UserSettingsRepositoryInterface
{
    /**
     * @var array<string, UserSettings>
     */
    private array $settings = [];

    public function save(UserSettings $settings): void
    {
        $this->settings[$settings->userId()->value()] = $settings;
    }

    public function findByUserId(Uuid $userId): ?UserSettings
    {
        return $this->settings[$userId->value()] ?? null;
    }

    public function delete(UserSettings $settings): void
    {
        unset($this->settings[$settings->userId()->value()]);
    }

    public function clear(): void
    {
        $this->settings = [];
    }
}
