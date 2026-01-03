<?php

declare(strict_types=1);

namespace App\Tests\UserSettings\Domain;

use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Shared\Mother\UuidMother;
use App\UserSettings\Domain\Entity\UserSettings;
use App\UserSettings\Domain\ValueObject\PreferenceOption;
use App\UserSettings\Domain\ValueObject\PreferenceType;
use App\UserSettings\Domain\ValueObject\UserPreference;
use App\UserSettings\Domain\ValueObject\UserPreferences;
use PHPUnit\Framework\TestCase;

class UserSettingsTest extends TestCase
{
    public function testCanCreateUserSettings(): void
    {
        $userId = UuidMother::random();
        $preferences = UserPreferences::defaultNotificationPreferences();

        $settings = UserSettings::create($userId, $preferences);

        $this->assertEquals($userId, $settings->userId());
        $this->assertEquals($preferences, $settings->preferences());
    }

    public function testCanCreateWithDefaultPreferences(): void
    {
        $userId = UuidMother::random();

        $settings = UserSettings::create($userId);
        $notificationPreference = $settings->getPreferenceByType(PreferenceType::notifications());

        $this->assertNotNull($notificationPreference);
        $this->assertTrue($notificationPreference->isOptionEnabled('email'));
    }

    public function testCanUpdatePreferences(): void
    {
        $userId = UuidMother::random();
        $settings = UserSettings::create($userId);

        $newPreference = UserPreference::create(
            PreferenceType::notifications(),
            [
                PreferenceOption::create('email', false),
                PreferenceOption::create('sms', true),
            ]
        );
        
        $settings->updatePreference($newPreference);
        $updatedPreference = $settings->getPreferenceByType(PreferenceType::notifications());

        $this->assertFalse($updatedPreference->isOptionEnabled('email'));
        $this->assertTrue($updatedPreference->isOptionEnabled('sms'));
    }

    public function testCanGetPreferenceByType(): void
    {
        $userId = UuidMother::random();
        $settings = UserSettings::create($userId);

        $preference = $settings->getPreferenceByType(PreferenceType::notifications());

        $this->assertNotNull($preference);
        $this->assertTrue($preference->type()->equals(PreferenceType::notifications()));
    }
}
