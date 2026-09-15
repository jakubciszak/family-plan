<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'push_subscriptions')]
#[ORM\UniqueConstraint(name: 'uniq_push_subscriptions_endpoint', columns: ['endpoint'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_push_subscriptions_user')]
class PushSubscription
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $userId,

        #[ORM\Column(type: 'string', length: 500)]
        private string $endpoint,

        #[ORM\Column(type: 'string', length: 255)]
        private string $publicKey,

        #[ORM\Column(type: 'string', length: 255)]
        private string $authToken,

        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $deviceLabel,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $lastUsedAt = null
    ) {
    }

    public static function register(
        Uuid $id,
        Uuid $userId,
        string $endpoint,
        string $publicKey,
        string $authToken,
        ?string $deviceLabel,
        DateTimeImmutable $createdAt
    ): self {
        if (trim($endpoint) === '') {
            throw new \InvalidArgumentException('Push endpoint cannot be empty');
        }

        if (trim($publicKey) === '' || trim($authToken) === '') {
            throw new \InvalidArgumentException('Push subscription keys cannot be empty');
        }

        return new self($id, $userId, $endpoint, $publicKey, $authToken, $deviceLabel, $createdAt);
    }

    public function handOverTo(Uuid $userId, string $publicKey, string $authToken, ?string $deviceLabel): void
    {
        $this->userId = $userId;
        $this->publicKey = $publicKey;
        $this->authToken = $authToken;
        $this->deviceLabel = $deviceLabel;
    }

    public function markUsed(DateTimeImmutable $usedAt): void
    {
        $this->lastUsedAt = $usedAt;
    }

    public function belongsTo(Uuid $userId): bool
    {
        return $this->userId->equals($userId);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    public function authToken(): string
    {
        return $this->authToken;
    }

    public function deviceLabel(): ?string
    {
        return $this->deviceLabel;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }
}
