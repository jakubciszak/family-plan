<?php

declare(strict_types=1);

namespace App\Tests\UserManagement\Domain;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

/**
 * Test for User activation functionality
 */
class UserActivationTest extends TestCase
{
    public function testUserCanBeActivatedWithCorrectToken(): void
    {
        // Given
        $user = User::register(
            Uuid::generate(),
            'Test User',
            Email::fromString('test@example.com'),
            password_hash('password123', PASSWORD_BCRYPT),
            null
        );

        $activationToken = $user->activationToken();
        $this->assertNotNull($activationToken);
        $this->assertFalse($user->isActive());

        // When
        $user->activate($activationToken);

        // Then
        $this->assertTrue($user->isActive());
        $this->assertNull($user->activationToken());
    }

    public function testUserActivationFailsWithWrongToken(): void
    {
        // Given
        $user = User::register(
            Uuid::generate(),
            'Test User',
            Email::fromString('test@example.com'),
            password_hash('password123', PASSWORD_BCRYPT),
            null
        );

        $this->assertFalse($user->isActive());

        // Expect exception
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invalid activation token');

        // When
        $user->activate('wrong-token');
    }

    public function testRegisteredUserHasActivationToken(): void
    {
        // Given & When
        $user = User::register(
            Uuid::generate(),
            'Test User',
            Email::fromString('test@example.com'),
            password_hash('password123', PASSWORD_BCRYPT),
            null
        );

        // Then
        $this->assertNotNull($user->activationToken());
        $this->assertIsString($user->activationToken());
        $this->assertGreaterThan(10, strlen($user->activationToken()));
    }

    public function testRegisteredUserIsNotActive(): void
    {
        // Given & When
        $user = User::register(
            Uuid::generate(),
            'Test User',
            Email::fromString('test@example.com'),
            password_hash('password123', PASSWORD_BCRYPT),
            null
        );

        // Then
        $this->assertFalse($user->isActive());
    }

    public function testCreatedUserIsActive(): void
    {
        // Given & When
        $user = User::create(
            Uuid::generate(),
            'Admin User',
            Email::fromString('admin@example.com'),
            password_hash('password123', PASSWORD_BCRYPT),
            \App\UserManagement\Domain\ValueObject\Role::ADMIN
        );

        // Then
        $this->assertTrue($user->isActive());
        $this->assertNull($user->activationToken());
    }

    public function testRegisteredUserWithPhoneNumber(): void
    {
        // Given & When
        $phoneNumber = '+48123456789';
        $user = User::register(
            Uuid::generate(),
            'Test User',
            Email::fromString('test@example.com'),
            password_hash('password123', PASSWORD_BCRYPT),
            $phoneNumber
        );

        // Then
        $this->assertEquals($phoneNumber, $user->phoneNumber());
    }
}
