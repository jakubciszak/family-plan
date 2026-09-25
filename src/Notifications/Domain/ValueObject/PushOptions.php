<?php

declare(strict_types=1);

namespace App\Notifications\Domain\ValueObject;

use DateTimeImmutable;

/**
 * How long a push service may keep a message for an offline device, and which older message it replaces.
 *
 * Without these a push service holds a message for four weeks, so a phone that comes back online
 * gets every old notification at once.
 */
final readonly class PushOptions
{
    public const DEFAULT_TTL = 43200;
    private const MIN_TTL = 60;
    private const URGENCIES = ['very-low', 'low', 'normal', 'high'];

    private function __construct(
        public int $ttl,
        public ?string $tag,
        public string $urgency
    ) {
    }

    public static function from(array $parameters, DateTimeImmutable $now): self
    {
        $ttl = DeliveryParameters::ttl($parameters) ?? self::DEFAULT_TTL;
        $expiresAt = DeliveryParameters::expiresAt($parameters);

        if ($expiresAt !== null) {
            $ttl = min($ttl, $expiresAt->getTimestamp() - $now->getTimestamp());
        }

        $tag = $parameters['tag'] ?? null;
        $urgency = $parameters[DeliveryParameters::URGENCY] ?? null;

        return new self(
            max(self::MIN_TTL, $ttl),
            is_string($tag) && $tag !== '' ? $tag : null,
            in_array($urgency, self::URGENCIES, true) ? $urgency : 'normal'
        );
    }

    /**
     * True when delivering now would show something already out of date:
     * the notification expired, or it waited in the queue longer than a push service would keep it.
     */
    public static function isOutdated(array $parameters, DateTimeImmutable $now): bool
    {
        $expiresAt = DeliveryParameters::expiresAt($parameters);

        if ($expiresAt !== null && $expiresAt <= $now) {
            return true;
        }

        $queuedAt = DeliveryParameters::queuedAt($parameters);
        $ttl = DeliveryParameters::ttl($parameters) ?? self::DEFAULT_TTL;

        return $queuedAt !== null && $now->getTimestamp() - $queuedAt->getTimestamp() > $ttl;
    }

    /**
     * Web Push topics are at most 32 characters of the URL-safe base64 alphabet (RFC 8030).
     */
    public function topic(): ?string
    {
        if ($this->tag === null) {
            return null;
        }

        return substr(rtrim(strtr(base64_encode(hash('sha256', $this->tag, true)), '+/', '-_'), '='), 0, 32);
    }
}
