<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Domain\ValueObject;

final readonly class NotificationChannels
{
    public const EMAIL = 'email';
    public const SMS = 'sms';
    public const IN_APP = 'in_app';

    private const SUPPORTED = [
        self::EMAIL,
        self::SMS,
        self::IN_APP,
    ];

    private const ENABLED_BY_DEFAULT_FOR_USER = [
        self::EMAIL => true,
        self::SMS => false,
        self::IN_APP => true,
    ];

    /**
     * @param list<string> $channels
     */
    private function __construct(
        private array $channels
    ) {
    }

    /**
     * @param iterable<string> $channels
     */
    public static function fromArray(iterable $channels): self
    {
        $selected = [];

        foreach ($channels as $channel) {
            if (!in_array($channel, self::SUPPORTED, true)) {
                throw new \InvalidArgumentException("Invalid notification channel: {$channel}");
            }

            $selected[$channel] = true;
        }

        return new self(array_values(array_filter(
            self::SUPPORTED,
            static fn(string $channel) => isset($selected[$channel])
        )));
    }

    public static function none(): self
    {
        return new self([]);
    }

    public static function email(): self
    {
        return new self([self::EMAIL]);
    }

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return self::SUPPORTED;
    }

    public static function isEnabledByDefaultForUser(string $channel): bool
    {
        return self::ENABLED_BY_DEFAULT_FOR_USER[$channel] ?? false;
    }

    public function contains(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }

    public function intersect(self $other): self
    {
        return new self(array_values(array_filter(
            $this->channels,
            static fn(string $channel) => $other->contains($channel)
        )));
    }

    public function isEmpty(): bool
    {
        return $this->channels === [];
    }

    public function equals(self $other): bool
    {
        return $this->channels === $other->channels;
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return $this->channels;
    }
}
