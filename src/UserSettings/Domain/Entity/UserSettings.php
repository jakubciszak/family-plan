<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\Entity;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\ValueObject\PreferenceType;
use App\UserSettings\Domain\ValueObject\UserPreference;
use App\UserSettings\Domain\ValueObject\UserPreferences;
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
        
        #[ORM\Column(type: 'user_preferences')]
        private UserPreferences $preferences,
        
        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,
        
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $updatedAt = null
    ) {
    }

    public static function create(
        Uuid $userId,
        ?UserPreferences $preferences = null
    ): self {
        return new self(
            null,
            $userId,
            $preferences ?? UserPreferences::defaultNotificationPreferences(),
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

    public function preferences(): UserPreferences
    {
        return $this->preferences;
    }

    public function updatePreferences(UserPreferences $preferences): void
    {
        $this->preferences = $preferences;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updatePreference(UserPreference $preference): void
    {
        $this->preferences = $this->preferences->update($preference);
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getPreferenceByType(PreferenceType $type): ?UserPreference
    {
        return $this->preferences->getByType($type);
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
