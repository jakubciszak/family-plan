<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Domain\ValueObject;

final readonly class NotificationEvent
{
    public const TASK_COMPLETED = 'task_completed';
    public const TASK_APPROVED = 'task_approved';
    public const USER_WELCOME = 'user_welcome';
    public const ACCOUNT_ACTIVATION = 'account_activation';
    public const PAYOUT_OFFERED = 'payout_offered';
    public const STREAK_AT_RISK = 'streak_at_risk';

    public const TASK_ASSIGNED = 'task_assigned';

    public const TASK_REJECTED = 'task_rejected';

    public const TASK_ABANDONED = 'task_abandoned';

    public const TASK_CORRECTED = 'task_corrected';

    public const TASK_REMOVED = 'task_removed';

    public const CALENDAR_CHANGED = 'calendar_changed';

    public const CALENDAR_REMOVED = 'calendar_removed';

    private const HOUR = 3600;

    /** Order of the groups in the notification settings of a user. */
    private const GROUPS = ['tasks', 'calendar', 'allowance', 'streaks'];

    /**
     * defaultChannels: what the application policy starts with; the admin can change them when configurable.
     * group: where the event sits in the user's notification settings; null keeps it out of them.
     * adminOnly: only team admins ever receive it.
     * pushTtl: seconds a push service may keep the message for an offline device.
     */
    private const CATALOG = [
        self::CALENDAR_CHANGED => [
            'defaultChannels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'configurable' => true,
            'group' => 'calendar',
            'pushTtl' => 12 * self::HOUR,
        ],
        self::CALENDAR_REMOVED => [
            'defaultChannels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'configurable' => true,
            'group' => 'calendar',
            'pushTtl' => 12 * self::HOUR,
        ],
        self::TASK_ASSIGNED => [
            'defaultChannels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'configurable' => true,
            'group' => 'tasks',
            'pushTtl' => 12 * self::HOUR,
        ],
        self::TASK_COMPLETED => [
            'defaultChannels' => [NotificationChannels::EMAIL, NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'configurable' => true,
            'group' => 'tasks',
            'adminOnly' => true,
            'pushTtl' => 12 * self::HOUR,
        ],
        self::TASK_REJECTED => [
            'defaultChannels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'configurable' => true,
            'group' => 'tasks',
            'pushTtl' => 24 * self::HOUR,
        ],
        self::TASK_APPROVED => [
            'defaultChannels' => [NotificationChannels::EMAIL, NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'configurable' => true,
            'group' => 'tasks',
            'pushTtl' => 12 * self::HOUR,
        ],
        self::TASK_ABANDONED => [
            'defaultChannels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'configurable' => true,
            'group' => 'tasks',
            'pushTtl' => 12 * self::HOUR,
        ],
        self::TASK_CORRECTED => [
            'defaultChannels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'configurable' => true,
            'group' => 'tasks',
            'pushTtl' => 12 * self::HOUR,
        ],
        self::TASK_REMOVED => [
            'defaultChannels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'configurable' => true,
            'group' => 'tasks',
            'pushTtl' => 12 * self::HOUR,
        ],
        self::USER_WELCOME => [
            'defaultChannels' => [NotificationChannels::EMAIL],
            'configurable' => true,
        ],
        self::ACCOUNT_ACTIVATION => [
            'defaultChannels' => [NotificationChannels::EMAIL],
            'configurable' => false,
        ],
        self::PAYOUT_OFFERED => [
            'defaultChannels' => [NotificationChannels::IN_APP, NotificationChannels::PUSH],
            'configurable' => true,
            'group' => 'allowance',
            'pushTtl' => 48 * self::HOUR,
        ],
        self::STREAK_AT_RISK => [
            'defaultChannels' => [NotificationChannels::PUSH],
            'configurable' => true,
            'group' => 'streaks',
            'pushTtl' => 6 * self::HOUR,
        ],
    ];

    private function __construct(
        private string $value
    ) {
    }

    public static function taskCompleted(): self
    {
        return new self(self::TASK_COMPLETED);
    }

    public static function taskApproved(): self
    {
        return new self(self::TASK_APPROVED);
    }

    public static function userWelcome(): self
    {
        return new self(self::USER_WELCOME);
    }

    public static function accountActivation(): self
    {
        return new self(self::ACCOUNT_ACTIVATION);
    }

    public static function payoutOffered(): self
    {
        return new self(self::PAYOUT_OFFERED);
    }

    public static function streakAtRisk(): self
    {
        return new self(self::STREAK_AT_RISK);
    }

    public static function fromString(string $value): self
    {
        if (!array_key_exists($value, self::CATALOG)) {
            throw new \InvalidArgumentException("Invalid notification event: {$value}");
        }

        return new self($value);
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return array_map(
            static fn(string $value) => new self($value),
            array_keys(self::CATALOG)
        );
    }

    public function value(): string
    {
        return $this->value;
    }

    public function defaultChannels(): NotificationChannels
    {
        return NotificationChannels::fromArray(self::CATALOG[$this->value]['defaultChannels']);
    }

    public function isConfigurable(): bool
    {
        return self::CATALOG[$this->value]['configurable'];
    }

    /**
     * Where the event sits in the notification settings of a user; null when the user cannot switch it off.
     */
    public function group(): ?string
    {
        return self::CATALOG[$this->value]['group'] ?? null;
    }

    public function isUserChoice(): bool
    {
        return $this->group() !== null;
    }

    public function isAdminOnly(): bool
    {
        return self::CATALOG[$this->value]['adminOnly'] ?? false;
    }

    public function pushTtl(): int
    {
        return self::CATALOG[$this->value]['pushTtl'] ?? 12 * self::HOUR;
    }

    /**
     * @return list<self>
     */
    public static function userChoices(): array
    {
        $choices = array_values(array_filter(self::all(), static fn (self $event) => $event->isUserChoice()));
        $position = array_flip(array_keys(self::CATALOG));

        usort($choices, static fn (self $a, self $b) => [array_search($a->group(), self::GROUPS, true), $position[$a->value]]
            <=> [array_search($b->group(), self::GROUPS, true), $position[$b->value]]);

        return $choices;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
