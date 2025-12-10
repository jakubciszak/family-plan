<?php

declare(strict_types=1);

namespace App\Tests\UserManagement\Domain;

use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\UserManagement\Assert\UserAssert;
use App\Tests\UserManagement\Mother\EmailMother;
use App\Tests\UserManagement\Mother\RoleMother;
use App\Tests\UserManagement\Mother\UserMother;
use PHPUnit\Framework\TestCase;

/**
 * Detroit school unit test for User Management functionality
 * Tests real object interactions without mocks using Object Mothers and custom Asserts
 */
class UserManagementTest extends TestCase
{
    public function testUserCanBeCreatedWithValidData(): void
    {
        // Given
        $id = UuidMother::random();
        $name = 'John Doe';
        $email = EmailMother::user();
        $role = RoleMother::user();

        // When
        $user = UserMother::create(id: $id, name: $name, email: $email, role: $role);

        // Then
        UserAssert::assertUserHasId($id, $user);
        UserAssert::assertUserHasName($name, $user);
        UserAssert::assertUserHasEmail($email, $user);
        UserAssert::assertUserHasRole($role, $user);
        UserAssert::assertUserIsNotAdmin($user);
        UserAssert::assertUserHasCreatedAt($user);
    }

    public function testUserCanBeCreatedAsAdmin(): void
    {
        // When
        $user = UserMother::admin();

        // Then
        UserAssert::assertUserIsAdmin($user);
        UserAssert::assertUserHasRole(RoleMother::admin(), $user);
    }

    public function testUserCreationRecordsDomainEvent(): void
    {
        // Given
        $id = UuidMother::random();
        $email = EmailMother::user();
        $role = RoleMother::user();

        // When
        $user = UserMother::create(id: $id, email: $email, role: $role);

        // Then
        UserAssert::assertUserRecordedUserCreatedEvent($user, $id, $email, $role);
    }

    public function testDomainEventsAreClearedAfterPulling(): void
    {
        // Given
        $user = UserMother::regularUser();

        // When
        $user->pullDomainEvents(); // First pull clears events
        
        // Then
        UserAssert::assertUserHasNoDomainEvents($user);
    }

    public function testUserNameCanBeUpdated(): void
    {
        // Given
        $user = UserMother::withName('Original Name');
        UserAssert::assertUserHasNoUpdatedAt($user);

        // When
        $user->updateName('New Name');

        // Then
        UserAssert::assertUserHasName('New Name', $user);
        UserAssert::assertUserHasUpdatedAt($user);
    }

    public function testUserEmailCanBeUpdated(): void
    {
        // Given
        $user = UserMother::regularUser();
        $newEmail = EmailMother::create('newemail@example.com');

        // When
        $user->updateEmail($newEmail);

        // Then
        UserAssert::assertUserHasEmail($newEmail, $user);
        UserAssert::assertUserHasUpdatedAt($user);
    }

    public function testUserPasswordCanBeChanged(): void
    {
        // Given
        $oldPassword = password_hash('oldpass', PASSWORD_BCRYPT);
        $user = UserMother::create(password: $oldPassword);
        $newPassword = password_hash('newpass', PASSWORD_BCRYPT);

        // When
        $user->changePassword($newPassword);

        // Then
        $this->assertSame($newPassword, $user->password());
        $this->assertNotSame($oldPassword, $user->password());
    }

    public function testUserCanBePromotedToAdmin(): void
    {
        // Given
        $user = UserMother::regularUser();
        UserAssert::assertUserIsNotAdmin($user);

        // When
        $user->promoteToAdmin();

        // Then
        UserAssert::assertUserIsAdmin($user);
        UserAssert::assertUserHasRole(RoleMother::admin(), $user);
        UserAssert::assertUserHasUpdatedAt($user);
    }

    public function testEmailValueObjectValidatesFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        Email::fromString('not-an-email');
    }

    public function testEmailValueObjectsAreComparable(): void
    {
        // Given
        $email1 = EmailMother::create('test@example.com');
        $email2 = EmailMother::create('test@example.com');
        $email3 = EmailMother::create('other@example.com');

        // Then
        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));
    }

    public function testRoleEnumCanBeCreatedFromString(): void
    {
        // When
        $userRole = Role::fromString('ROLE_USER');
        $adminRole = Role::fromString('ROLE_ADMIN');

        // Then
        $this->assertSame(Role::USER, $userRole);
        $this->assertSame(Role::ADMIN, $adminRole);
        $this->assertTrue($adminRole->isAdmin());
        $this->assertFalse($userRole->isAdmin());
    }

    public function testInvalidRoleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid role');

        Role::fromString('INVALID_ROLE');
    }

    public function testUuidCanBeGenerated(): void
    {
        // When
        $uuid1 = UuidMother::random();
        $uuid2 = UuidMother::random();

        // Then
        UserAssert::assertUuidIsValid($uuid1->value());
        $this->assertFalse($uuid1->equals($uuid2));
    }

    public function testUuidCanBeCreatedFromString(): void
    {
        // When
        $uuid = UuidMother::fixed();

        // Then
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $uuid->value());
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $uuid);
    }

    public function testInvalidUuidFormatThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID format');

        UuidMother::create('not-a-uuid');
    }
}
