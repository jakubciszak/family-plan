<?php

declare(strict_types=1);

namespace App\Tests\UserSettings\Domain;

use App\UserSettings\Domain\ValueObject\PreferenceOption;
use App\UserSettings\Domain\ValueObject\PreferenceType;
use App\UserSettings\Domain\ValueObject\UserPreference;
use PHPUnit\Framework\TestCase;

class UserPreferenceTest extends TestCase
{
    public function testCanCreateUserPreference(): void
    {
        $type = PreferenceType::notifications();
        $options = [
            PreferenceOption::create('email', true),
            PreferenceOption::create('sms', false),
        ];
        
        $preference = UserPreference::create($type, $options);
        
        $this->assertTrue($preference->type()->equals($type));
        $this->assertCount(2, $preference->options());
    }

    public function testCanGetEnabledOptions(): void
    {
        $type = PreferenceType::notifications();
        $options = [
            PreferenceOption::create('email', true),
            PreferenceOption::create('sms', false),
            PreferenceOption::create('push', true),
        ];
        
        $preference = UserPreference::create($type, $options);
        $enabled = $preference->getEnabledOptions();
        
        $this->assertCount(2, $enabled);
        $this->assertEquals('email', $enabled[0]->name());
        $this->assertEquals('push', $enabled[1]->name());
    }

    public function testCanCheckIfOptionEnabled(): void
    {
        $type = PreferenceType::notifications();
        $options = [
            PreferenceOption::create('email', true),
            PreferenceOption::create('sms', false),
        ];
        
        $preference = UserPreference::create($type, $options);
        
        $this->assertTrue($preference->isOptionEnabled('email'));
        $this->assertFalse($preference->isOptionEnabled('sms'));
        $this->assertFalse($preference->isOptionEnabled('nonexistent'));
    }

    public function testCanUpdateOption(): void
    {
        $type = PreferenceType::notifications();
        $options = [
            PreferenceOption::create('email', false),
        ];
        
        $preference = UserPreference::create($type, $options);
        $updated = $preference->updateOption('email', true);
        
        $this->assertTrue($updated->isOptionEnabled('email'));
    }
}
