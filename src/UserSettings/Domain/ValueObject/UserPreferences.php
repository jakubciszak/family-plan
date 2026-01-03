<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\ValueObject;

final readonly class UserPreferences
{
    /**
     * @param UserPreference[] $preferences
     */
    private function __construct(
        private array $preferences
    ) {
    }

    /**
     * @param UserPreference[] $preferences
     */
    public static function create(array $preferences): self
    {
        return new self($preferences);
    }

    public static function defaultNotificationPreferences(): self
    {
        $notificationsPreference = UserPreference::create(
            PreferenceType::notifications(),
            [
                PreferenceOption::create('email', true),
                PreferenceOption::create('sms', false),
            ]
        );

        return new self([$notificationsPreference]);
    }

    /**
     * @return UserPreference[]
     */
    public function all(): array
    {
        return $this->preferences;
    }

    public function getByType(PreferenceType $type): ?UserPreference
    {
        foreach ($this->preferences as $preference) {
            if ($preference->type()->equals($type)) {
                return $preference;
            }
        }
        return null;
    }

    public function add(UserPreference $preference): self
    {
        $preferences = $this->preferences;
        $preferences[] = $preference;
        
        return new self($preferences);
    }

    public function update(UserPreference $updatedPreference): self
    {
        $preferences = array_map(
            fn(UserPreference $p) => 
                $p->type()->equals($updatedPreference->type()) 
                    ? $updatedPreference 
                    : $p,
            $this->preferences
        );

        return new self($preferences);
    }

    public function toArray(): array
    {
        return array_map(
            fn(UserPreference $preference) => $preference->toArray(),
            $this->preferences
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            array_map(
                fn(array $preferenceData) => UserPreference::fromArray($preferenceData),
                $data
            )
        );
    }
}
