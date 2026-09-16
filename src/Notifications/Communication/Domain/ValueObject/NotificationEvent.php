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

    private const CATALOG = [
        self::TASK_COMPLETED => [
            'defaultChannels' => [NotificationChannels::EMAIL],
            'configurable' => true,
        ],
        self::TASK_APPROVED => [
            'defaultChannels' => [NotificationChannels::EMAIL],
            'configurable' => true,
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
        ],
        self::STREAK_AT_RISK => [
            'defaultChannels' => [NotificationChannels::PUSH],
            'configurable' => true,
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

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
