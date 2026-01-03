<?php

declare(strict_types=1);

namespace App\Tests\UserSettings\Domain;

use App\UserSettings\Domain\ValueObject\PreferenceType;
use PHPUnit\Framework\TestCase;

class PreferenceTypeTest extends TestCase
{
    public function testCanCreateNotificationsType(): void
    {
        $type = PreferenceType::notifications();
        
        $this->assertEquals('notifications', $type->value());
    }

    public function testCanCreateFromString(): void
    {
        $type = PreferenceType::fromString('notifications');
        
        $this->assertEquals('notifications', $type->value());
    }

    public function testThrowsExceptionForInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid preference type: invalid');
        
        PreferenceType::fromString('invalid');
    }

    public function testCanCompareTypes(): void
    {
        $type1 = PreferenceType::notifications();
        $type2 = PreferenceType::notifications();
        
        $this->assertTrue($type1->equals($type2));
    }
}
