<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Domain\ValueObject;

use App\UserSettings\Domain\ValueObject\PreferenceOption;
use App\UserSettings\Domain\ValueObject\UserPreference;

/**
 * Which kinds of notification a user wants. Everything is on until the user switches it off,
 * and events the user cannot choose (account emails) are always on.
 */
final readonly class EventChoices
{
    /**
     * @param array<string, bool> $choices
     */
    private function __construct(private array $choices)
    {
    }

    public static function from(?UserPreference $preference): self
    {
        $choices = [];

        foreach ($preference?->options() ?? [] as $option) {
            /** @var PreferenceOption $option */
            $choices[$option->name()] = $option->isEnabled();
        }

        return new self($choices);
    }

    public function wants(NotificationEvent $event): bool
    {
        if (!$event->isUserChoice()) {
            return true;
        }

        return $this->choices[$event->value()] ?? true;
    }

    /**
     * @param array<string, mixed> $changes event name => enabled
     */
    public function with(array $changes): self
    {
        $choices = $this->choices;

        foreach ($changes as $name => $enabled) {
            $event = NotificationEvent::fromString((string) $name);

            if (!$event->isUserChoice()) {
                throw new \InvalidArgumentException(sprintf('Notification event "%s" cannot be switched off', $event->value()));
            }

            if (!is_bool($enabled)) {
                throw new \InvalidArgumentException(sprintf('Choice for "%s" must be true or false', $event->value()));
            }

            $choices[$event->value()] = $enabled;
        }

        return new self($choices);
    }

    /**
     * @return list<PreferenceOption>
     */
    public function toOptions(): array
    {
        $options = [];

        foreach (NotificationEvent::userChoices() as $event) {
            $options[] = PreferenceOption::create($event->value(), $this->wants($event));
        }

        return $options;
    }
}
