<?php

declare(strict_types=1);

namespace App\Tests\UserSettings\Domain;

use App\UserSettings\Domain\ValueObject\PreferenceOption;
use App\UserSettings\Domain\ValueObject\PreferenceType;
use App\UserSettings\Domain\ValueObject\UserPreference;
use App\UserSettings\Domain\ValueObject\UserPreferences;
use PHPUnit\Framework\TestCase;

class UserPreferencesTest extends TestCase
{
    public function testCanCreateEmptyPreferences(): void
    {
        $preferences = UserPreferences::create([]);
        
        $this->assertCount(0, $preferences->all());
    }

    public function testCanCreateWithMultiplePreferences(): void
    {
        $notificationsPreference = UserPreference::create(
            PreferenceType::notifications(),
            [PreferenceOption::create('email', true)]
        );
        
        $preferences = UserPreferences::create([$notificationsPreference]);
        
        $this->assertCount(1, $preferences->all());
    }

    public function testCanGetPreferenceByType(): void
    {
        $notificationsPreference = UserPreference::create(
            PreferenceType::notifications(),
            [PreferenceOption::create('email', true)]
        );
        
        $preferences = UserPreferences::create([$notificationsPreference]);
        $found = $preferences->getByType(PreferenceType::notifications());
        
        $this->assertNotNull($found);
        $this->assertTrue($found->type()->equals(PreferenceType::notifications()));
    }

    public function testReturnsNullForNonexistentType(): void
    {
        $preferences = UserPreferences::create([]);
        $found = $preferences->getByType(PreferenceType::notifications());
        
        $this->assertNull($found);
    }

    public function testCanAddPreference(): void
    {
        $preferences = UserPreferences::create([]);
        $notificationsPreference = UserPreference::create(
            PreferenceType::notifications(),
            [PreferenceOption::create('email', true)]
        );
        
        $updated = $preferences->add($notificationsPreference);
        
        $this->assertCount(1, $updated->all());
    }

    public function testCanUpdateExistingPreference(): void
    {
        $initialPreference = UserPreference::create(
            PreferenceType::notifications(),
            [PreferenceOption::create('email', false)]
        );
        
        $preferences = UserPreferences::create([$initialPreference]);
        
        $updatedPreference = UserPreference::create(
            PreferenceType::notifications(),
            [PreferenceOption::create('email', true)]
        );
        
        $updated = $preferences->update($updatedPreference);
        $found = $updated->getByType(PreferenceType::notifications());
        
        $this->assertTrue($found->isOptionEnabled('email'));
    }

    public function testCanCreateDefaultNotificationPreferences(): void
    {
        $preferences = UserPreferences::defaultNotificationPreferences();
        $notifications = $preferences->getByType(PreferenceType::notifications());
        
        $this->assertNotNull($notifications);
        $this->assertTrue($notifications->isOptionEnabled('email'));
        $this->assertFalse($notifications->isOptionEnabled('sms'));
    }
}
