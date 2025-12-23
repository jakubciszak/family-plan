<?php

declare(strict_types=1);

namespace App\Tests\UserManagement\Domain;

use App\Tests\UserManagement\Mother\UserMother;
use PHPUnit\Framework\TestCase;

/**
 * Test for User points functionality
 */
class UserPointsTest extends TestCase
{
    public function testUserStartsWithZeroPoints(): void
    {
        // When
        $user = UserMother::regularUser();

        // Then
        $this->assertEquals(0, $user->points());
    }

    public function testUserCanReceivePoints(): void
    {
        // Given
        $user = UserMother::regularUser();

        // When
        $user->addPoints(50);

        // Then
        $this->assertEquals(50, $user->points());
    }

    public function testPointsAccumulate(): void
    {
        // Given
        $user = UserMother::regularUser();

        // When
        $user->addPoints(30);
        $user->addPoints(20);
        $user->addPoints(10);

        // Then
        $this->assertEquals(60, $user->points());
    }

    public function testCannotAddNegativePoints(): void
    {
        // Given
        $user = UserMother::regularUser();

        // Then
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot add negative points');

        // When
        $user->addPoints(-10);
    }

    public function testAddingPointsUpdatesTimestamp(): void
    {
        // Given
        $user = UserMother::regularUser();
        $initialUpdatedAt = $user->updatedAt();

        // When
        sleep(1); // Ensure timestamp difference
        $user->addPoints(10);

        // Then
        $this->assertNotNull($user->updatedAt());
        $this->assertNotEquals($initialUpdatedAt, $user->updatedAt());
    }
}
