<?php

declare(strict_types=1);

namespace App\Tests\UserSettings\Domain;

use App\UserSettings\Domain\ValueObject\PreferenceOption;
use PHPUnit\Framework\TestCase;

class PreferenceOptionTest extends TestCase
{
    public function testCanCreateEmailOption(): void
    {
        $option = PreferenceOption::create('email', true);
        
        $this->assertEquals('email', $option->name());
        $this->assertTrue($option->isEnabled());
    }

    public function testCanCreateSmsOption(): void
    {
        $option = PreferenceOption::create('sms', false);
        
        $this->assertEquals('sms', $option->name());
        $this->assertFalse($option->isEnabled());
    }

    public function testCanEnableOption(): void
    {
        $option = PreferenceOption::create('email', false);
        $enabled = $option->enable();
        
        $this->assertTrue($enabled->isEnabled());
    }

    public function testCanDisableOption(): void
    {
        $option = PreferenceOption::create('email', true);
        $disabled = $option->disable();
        
        $this->assertFalse($disabled->isEnabled());
    }

    public function testThrowsExceptionForEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        PreferenceOption::create('', true);
    }
}
