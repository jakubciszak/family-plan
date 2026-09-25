<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Application\Service;

use App\Notifications\Communication\Domain\ValueObject\EventChoices;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Service\TeamMates;
use App\UserSettings\Domain\Entity\UserSettings;
use App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface;
use App\UserSettings\Domain\ValueObject\PreferenceType;
use App\UserSettings\Domain\ValueObject\UserPreference;

/**
 * The kinds of notification a user can switch on and off in their settings.
 */
final readonly class NotificationPreferences
{
    public function __construct(
        private UserSettingsRepositoryInterface $settings,
        private NotificationPolicyProvider $policies,
        private TeamMates $teamMates
    ) {
    }

    /**
     * @return list<array{event: string, group: string, enabled: bool, channels: list<string>, relevant: bool}>
     */
    public function of(Uuid $userId): array
    {
        $choices = $this->choicesOf($userId);
        $administers = $this->teamMates->administersAnyTeam($userId);

        return array_map(
            fn (NotificationEvent $event) => [
                'event' => $event->value(),
                'group' => (string) $event->group(),
                'enabled' => $choices->wants($event),
                // What the application sends it through, so the user knows what switching it off silences.
                'channels' => $this->policies->channelsFor($event)->toArray(),
                // Approval requests only ever reach team admins.
                'relevant' => !$event->isAdminOnly() || $administers,
            ],
            NotificationEvent::userChoices()
        );
    }

    /**
     * @param array<string, mixed> $changes event name => enabled
     */
    public function change(Uuid $userId, array $changes): void
    {
        $choices = $this->choicesOf($userId)->with($changes);
        $settings = $this->settings->findByUserId($userId) ?? UserSettings::create($userId);

        $settings->updatePreference(UserPreference::create(PreferenceType::notificationEvents(), $choices->toOptions()));
        $this->settings->save($settings);
    }

    private function choicesOf(Uuid $userId): EventChoices
    {
        return EventChoices::from(
            $this->settings->findByUserId($userId)?->getPreferenceByType(PreferenceType::notificationEvents())
        );
    }
}
