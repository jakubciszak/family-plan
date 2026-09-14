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
        private ?DateTimeImmutable $readAt = null
    ) {
    }

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

        return new self($id, $userId, $message, $subject, $parameters, $createdAt);
    }

    public function markAsRead(DateTimeImmutable $readAt): void
    {
        if ($this->readAt !== null) {
            return;
        }

        $this->readAt = $readAt;
    }

    public function belongsTo(Uuid $userId): bool
    {
        return $this->userId->equals($userId);
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
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
}
