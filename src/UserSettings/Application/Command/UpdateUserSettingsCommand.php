<?php

declare(strict_types=1);

namespace App\UserSettings\Application\Command;

final readonly class UpdateUserSettingsCommand
{
    public function __construct(
        public string $userId,
        public string $preferenceType,
        public array $options // Array of ['name' => string, 'enabled' => bool]
    ) {
    }
}
