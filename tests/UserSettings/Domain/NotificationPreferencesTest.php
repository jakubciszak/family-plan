<?php

declare(strict_types=1);

namespace App\Tests\UserSettings\Domain;

use App\UserSettings\Domain\ValueObject\NotificationPreferences;
use PHPUnit\Framework\TestCase;

class NotificationPreferencesTest extends TestCase
{
    public function testCanCreateWithBothEnabled(): void
    {
        $preferences = NotificationPreferences::create(true, true);

        $this->assertTrue($preferences->isEmailEnabled());
        $this->assertTrue($preferences->isSmsEnabled());
    }

    public function testCanCreateWithOnlyEmailEnabled(): void
    {
        $preferences = NotificationPreferences::create(true, false);

        $this->assertTrue($preferences->isEmailEnabled());
        $this->assertFalse($preferences->isSmsEnabled());
    }

    public function testCanCreateWithOnlySmsEnabled(): void
    {
        $preferences = NotificationPreferences::create(false, true);

        $this->assertFalse($preferences->isEmailEnabled());
        $this->assertTrue($preferences->isSmsEnabled());
    }

    public function testCanCreateWithBothDisabled(): void
    {
        $preferences = NotificationPreferences::create(false, false);

        $this->assertFalse($preferences->isEmailEnabled());
        $this->assertFalse($preferences->isSmsEnabled());
    }

    public function testCanGetEnabledChannels(): void
    {
        $preferencesAll = NotificationPreferences::create(true, true);
        $this->assertEquals(['email', 'sms'], $preferencesAll->getEnabledChannels());

        $preferencesEmail = NotificationPreferences::create(true, false);
        $this->assertEquals(['email'], $preferencesEmail->getEnabledChannels());

        $preferencesSms = NotificationPreferences::create(false, true);
        $this->assertEquals(['sms'], $preferencesSms->getEnabledChannels());

        $preferencesNone = NotificationPreferences::create(false, false);
        $this->assertEquals([], $preferencesNone->getEnabledChannels());
    }

    public function testCanCheckIfAnyChannelEnabled(): void
    {
        $preferencesEnabled = NotificationPreferences::create(true, false);
        $this->assertTrue($preferencesEnabled->hasAnyChannelEnabled());

        $preferencesDisabled = NotificationPreferences::create(false, false);
        $this->assertFalse($preferencesDisabled->hasAnyChannelEnabled());
    }
}
