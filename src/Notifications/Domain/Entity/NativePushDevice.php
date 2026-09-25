<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A phone running the mobile app, reachable through Firebase Cloud Messaging even when the app is closed.
 */
#[ORM\Entity]
#[ORM\Table(name: 'native_push_devices')]
#[ORM\UniqueConstraint(name: 'uniq_native_push_devices_token', columns: ['token'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_native_push_devices_user')]
class NativePushDevice
{
    public const ANDROID = 'android';
    private const PLATFORMS = [self::ANDROID];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $userId,

        #[ORM\Column(type: 'string', length: 20)]
        private string $platform,

        #[ORM\Column(type: 'string', length: 512)]
        private string $token,

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
        string $platform,
        string $token,
        ?string $deviceLabel,
        DateTimeImmutable $createdAt
    ): self {
        if (!in_array($platform, self::PLATFORMS, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported push platform: %s', $platform));
        }

        if (trim($token) === '') {
            throw new \InvalidArgumentException('Device push token cannot be empty');
        }

        return new self($id, $userId, $platform, trim($token), $deviceLabel, $createdAt);
    }

    /**
     * One phone is one device: when someone else signs in on it, it starts receiving their notifications.
     */
    public function handOverTo(Uuid $userId, ?string $deviceLabel): void
    {
        $this->userId = $userId;
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

    public function platform(): string
    {
        return $this->platform;
    }

    public function token(): string
    {
        return $this->token;
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
