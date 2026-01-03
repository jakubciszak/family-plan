<?php

declare(strict_types=1);

namespace App\UserSettings\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Application\Command\UpdateUserSettingsCommand;
use App\UserSettings\Domain\Entity\UserSettings;
use App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface;
use App\UserSettings\Domain\ValueObject\PreferenceOption;
use App\UserSettings\Domain\ValueObject\PreferenceType;
use App\UserSettings\Domain\ValueObject\UserPreference;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateUserSettingsHandler
{
    public function __construct(
        private UserSettingsRepositoryInterface $userSettingsRepository
    ) {
    }

    public function __invoke(UpdateUserSettingsCommand $command): void
    {
        $userId = Uuid::fromString($command->userId);
        $settings = $this->userSettingsRepository->findByUserId($userId);

        // Convert options array to PreferenceOption objects
        $options = array_map(
            fn(array $opt) => PreferenceOption::create($opt['name'], $opt['enabled']),
            $command->options
        );

        $preference = UserPreference::create(
            PreferenceType::fromString($command->preferenceType),
            $options
        );

        if ($settings === null) {
            $settings = UserSettings::create($userId);
        }
        
        $settings->updatePreference($preference);
        $this->userSettingsRepository->save($settings);
    }
}
