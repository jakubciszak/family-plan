<?php

declare(strict_types=1);

namespace App\Tests\UserSettings\Domain;

use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Shared\Mother\UuidMother;
use App\UserSettings\Domain\Entity\UserSettings;
use App\UserSettings\Domain\ValueObject\NotificationPreferences;
use PHPUnit\Framework\TestCase;

class UserSettingsTest extends TestCase
{
    public function testCanCreateUserSettings(): void
    {
        $userId = UuidMother::random();
        $preferences = NotificationPreferences::create(
            emailEnabled: true,
            smsEnabled: false
        );

        $settings = UserSettings::create($userId, $preferences);

        $this->assertEquals($userId, $settings->userId());
        $this->assertEquals($preferences, $settings->notificationPreferences());
    }

    public function testCanUpdateNotificationPreferences(): void
    {
        $userId = UuidMother::random();
        $initialPreferences = NotificationPreferences::create(true, false);
        $settings = UserSettings::create($userId, $initialPreferences);

        $newPreferences = NotificationPreferences::create(false, true);
        $settings->updateNotificationPreferences($newPreferences);

        $this->assertEquals($newPreferences, $settings->notificationPreferences());
    }

    public function testCanEnableEmailNotifications(): void
    {
        $userId = UuidMother::random();
        $preferences = NotificationPreferences::create(false, false);
        $settings = UserSettings::create($userId, $preferences);

        $settings->enableEmailNotifications();

        $this->assertTrue($settings->notificationPreferences()->isEmailEnabled());
    }

    public function testCanDisableEmailNotifications(): void
    {
        $userId = UuidMother::random();
        $preferences = NotificationPreferences::create(true, false);
        $settings = UserSettings::create($userId, $preferences);

        $settings->disableEmailNotifications();

        $this->assertFalse($settings->notificationPreferences()->isEmailEnabled());
    }

    public function testCanEnableSmsNotifications(): void
    {
        $userId = UuidMother::random();
        $preferences = NotificationPreferences::create(false, false);
        $settings = UserSettings::create($userId, $preferences);

        $settings->enableSmsNotifications();

        $this->assertTrue($settings->notificationPreferences()->isSmsEnabled());
    }

    public function testCanDisableSmsNotifications(): void
    {
        $userId = UuidMother::random();
        $preferences = NotificationPreferences::create(false, true);
        $settings = UserSettings::create($userId, $preferences);

        $settings->disableSmsNotifications();

        $this->assertFalse($settings->notificationPreferences()->isSmsEnabled());
    }
}
