<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'in_app_notifications')]
#[ORM\Index(columns: ['user_id', 'read_at'], name: 'idx_in_app_notifications_user_read')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_in_app_notifications_user_created')]
#[ORM\Index(columns: ['topic'], name: 'idx_in_app_notifications_topic')]
class InAppNotification
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $userId,

        #[ORM\Column(type: 'text')]
        private string $message,

        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $subject,

        #[ORM\Column(type: 'json')]
        private array $parameters,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $readAt = null,

        /** Which application event raised it, e.g. task_completed. */
        #[ORM\Column(type: 'string', length: 50, nullable: true)]
        private ?string $event = null,

        /** What it is about, e.g. task-<id>: a newer notification on the same topic replaces this one. */
        #[ORM\Column(type: 'string', length: 120, nullable: true)]
        private ?string $topic = null,

        /** After this moment the notification says nothing true any more, e.g. a streak warning after midnight. */
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $expiresAt = null,

        /** When the thing it asked for got handled, by anyone, or a newer notification took its place. */
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $resolvedAt = null
    ) {
    }

    /**
     * `event`, `tag` and `expires_at` (ATOM) in the parameters describe the notification itself.
     */
    public static function raise(
        Uuid $id,
        Uuid $userId,
        string $message,
        ?string $subject,
        array $parameters,
        DateTimeImmutable $createdAt
    ): self {
        if (trim($message) === '') {
            throw new \InvalidArgumentException('Notification message cannot be empty');
        }

        return new self(
            $id,
            $userId,
            $message,
            $subject,
            $parameters,
            $createdAt,
            null,
            self::text($parameters['event'] ?? null, 50),
            self::text($parameters['tag'] ?? null, 120),
            self::moment($parameters['expires_at'] ?? null)
        );
    }

    public function markAsRead(DateTimeImmutable $readAt): void
    {
        if ($this->readAt !== null) {
            return;
        }

        $this->readAt = $readAt;
    }

    /**
     * Nothing is left to do about it, so it stops counting as unread on every device.
     */
    public function resolve(DateTimeImmutable $resolvedAt): void
    {
        if ($this->resolvedAt !== null) {
            return;
        }

        $this->resolvedAt = $resolvedAt;
        $this->markAsRead($resolvedAt);
    }

    public function belongsTo(Uuid $userId): bool
    {
        return $this->userId->equals($userId);
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function isResolved(): bool
    {
        return $this->resolvedAt !== null;
    }

    public function hasExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= $now;
    }

    /**
     * Still news to the recipient: not read, not handled and not out of date.
     */
    public function isActive(DateTimeImmutable $now): bool
    {
        return !$this->isRead() && !$this->isResolved() && !$this->hasExpired($now);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function subject(): ?string
    {
        return $this->subject;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function readAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function event(): ?string
    {
        return $this->event;
    }

    public function topic(): ?string
    {
        return $this->topic;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function resolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    private static function text(mixed $value, int $length): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $length) : null;
    }

    private static function moment(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $moment = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);

        return $moment === false ? null : $moment;
    }
}
