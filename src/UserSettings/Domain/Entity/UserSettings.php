<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\ValueObject\NotificationPreferences;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_settings')]
#[ORM\Index(columns: ['user_id'])]
class UserSettings
{
    private function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(type: 'integer')]
        private ?int $id,
        
        #[ORM\Column(type: 'uuid', unique: true)]
        private Uuid $userId,
        
        #[ORM\Column(type: 'notification_preferences')]
        private NotificationPreferences $notificationPreferences,
        
        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,
        
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $updatedAt = null
    ) {
    }

    public static function create(
        Uuid $userId,
        ?NotificationPreferences $notificationPreferences = null
    ): self {
        return new self(
            null,
            $userId,
            $notificationPreferences ?? NotificationPreferences::default(),
            new DateTimeImmutable()
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function notificationPreferences(): NotificationPreferences
    {
        return $this->notificationPreferences;
    }

    public function updateNotificationPreferences(NotificationPreferences $preferences): void
    {
        $this->notificationPreferences = $preferences;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function enableEmailNotifications(): void
    {
        $this->notificationPreferences = NotificationPreferences::create(
            true,
            $this->notificationPreferences->isSmsEnabled()
        );
        $this->updatedAt = new DateTimeImmutable();
    }

    public function disableEmailNotifications(): void
    {
        $this->notificationPreferences = NotificationPreferences::create(
            false,
            $this->notificationPreferences->isSmsEnabled()
        );
        $this->updatedAt = new DateTimeImmutable();
    }

    public function enableSmsNotifications(): void
    {
        $this->notificationPreferences = NotificationPreferences::create(
            $this->notificationPreferences->isEmailEnabled(),
            true
        );
        $this->updatedAt = new DateTimeImmutable();
    }

    public function disableSmsNotifications(): void
    {
        $this->notificationPreferences = NotificationPreferences::create(
            $this->notificationPreferences->isEmailEnabled(),
            false
        );
        $this->updatedAt = new DateTimeImmutable();
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
