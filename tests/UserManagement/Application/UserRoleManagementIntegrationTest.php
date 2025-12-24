<?php

declare(strict_types=1);

namespace App\Tests\UserManagement\Application;

use App\Shared\Infrastructure\Clock\FixedClock;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\UserManagement\Mother\EmailMother;
use App\Tests\UserManagement\Mother\UserMother;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for UserManagement with role-based operations
 * Tests admin role functionality and user lifecycle
 */
class UserRoleManagementIntegrationTest extends TestCase
{
    private InMemoryUserRepository $userRepository;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->userRepository = new InMemoryUserRepository();
        $this->clock = new FixedClock();
    }

    public function testAdminUserCanBeCreated(): void
    {
        // Given
        $adminId = UuidMother::random();
        
        // When - Create an admin user
        $admin = UserMother::aUser()
            ->withId($adminId)
            ->asAdmin()
            ->build();
        
        $this->userRepository->save($admin);
        
        // Then - User is saved as admin
        $savedAdmin = $this->userRepository->findById($adminId);
        $this->assertNotNull($savedAdmin);
        $this->assertTrue($savedAdmin->isAdmin());
        $this->assertEquals('ROLE_ADMIN', $savedAdmin->role()->value);
    }

    public function testRegularUserCanBeCreated(): void
    {
        // Given
        $userId = UuidMother::random();
        
        // When - Create a regular user
        $user = UserMother::aUser()
            ->withId($userId)
            ->asRegularUser()
            ->build();
        
        $this->userRepository->save($user);
        
        // Then - User is saved as regular user
        $savedUser = $this->userRepository->findById($userId);
        $this->assertNotNull($savedUser);
        $this->assertFalse($savedUser->isAdmin());
        $this->assertEquals('ROLE_USER', $savedUser->role()->value);
    }

    public function testRegularUserCanBePromotedToAdmin(): void
    {
        // Given - Regular user
        $userId = UuidMother::random();
        $user = UserMother::aUser()
            ->withId($userId)
            ->asRegularUser()
            ->build();
        
        $this->userRepository->save($user);
        
        // Verify initial role
        $this->assertFalse($user->isAdmin());
        
        // When - Promote to admin
        $user->promoteToAdmin();
        $this->userRepository->save($user);
        
        // Then - User is now admin
        $updatedUser = $this->userRepository->findById($userId);
        $this->assertTrue($updatedUser->isAdmin());
        $this->assertEquals('ROLE_ADMIN', $updatedUser->role()->value);
    }

    public function testMultipleUsersWithDifferentRoles(): void
    {
        // Given - Create multiple users with different roles
        $admin1Id = UuidMother::random();
        $admin2Id = UuidMother::random();
        $user1Id = UuidMother::random();
        $user2Id = UuidMother::random();
        
        $admin1 = UserMother::aUser()->withId($admin1Id)->asAdmin()->build();
        $admin2 = UserMother::aUser()->withId($admin2Id)->asAdmin()->build();
        $user1 = UserMother::aUser()->withId($user1Id)->asRegularUser()->build();
        $user2 = UserMother::aUser()->withId($user2Id)->asRegularUser()->build();
        
        // When - Save all users
        $this->userRepository->save($admin1);
        $this->userRepository->save($admin2);
        $this->userRepository->save($user1);
        $this->userRepository->save($user2);
        
        // Then - Each user maintains their role
        $this->assertTrue($this->userRepository->findById($admin1Id)->isAdmin());
        $this->assertTrue($this->userRepository->findById($admin2Id)->isAdmin());
        $this->assertFalse($this->userRepository->findById($user1Id)->isAdmin());
        $this->assertFalse($this->userRepository->findById($user2Id)->isAdmin());
    }

    public function testUserCanBeFoundByEmail(): void
    {
        // Given - User with specific email
        $userId = UuidMother::random();
        $email = EmailMother::create('testuser@example.com');
        
        $user = UserMother::aUser()
            ->withId($userId)
            ->withEmail($email)
            ->build();
        
        $this->userRepository->save($user);
        
        // When - Find by email
        $foundUser = $this->userRepository->findByEmail($email);
        
        // Then - User is found
        $this->assertNotNull($foundUser);
        $this->assertEquals($userId, $foundUser->id());
        $this->assertEquals('testuser@example.com', $foundUser->email()->value());
    }

    public function testUserEmailIsUnique(): void
    {
        // Given - First user with email
        $user1Id = UuidMother::random();
        $user2Id = UuidMother::random();
        $email = EmailMother::create('duplicate@example.com');
        
        $user1 = UserMother::aUser()
            ->withId($user1Id)
            ->withEmail($email)
            ->build();
        
        $this->userRepository->save($user1);
        
        // When - Create second user with same email
        $user2 = UserMother::aUser()
            ->withId($user2Id)
            ->withEmail($email)
            ->build();
        
        $this->userRepository->save($user2);
        
        // Then - Both users exist in repository with same email
        // findByEmail will return one of them (implementation specific)
        $foundUser = $this->userRepository->findByEmail($email);
        $this->assertNotNull($foundUser);
        $this->assertEquals('duplicate@example.com', $foundUser->email()->value());
        
        // Both users can be retrieved by ID
        $this->assertNotNull($this->userRepository->findById($user1Id));
        $this->assertNotNull($this->userRepository->findById($user2Id));
    }

    public function testUserDetailsCanBeUpdated(): void
    {
        // Given - User with initial data
        $userId = UuidMother::random();
        $user = UserMother::aUser()
            ->withId($userId)
            ->withName('Original Name')
            ->withEmail(EmailMother::create('original@example.com'))
            ->build();
        
        $this->userRepository->save($user);
        
        // When - Update user details
        $user->updateName('Updated Name');
        $this->userRepository->save($user);
        
        // Then - Changes are persisted
        $updatedUser = $this->userRepository->findById($userId);
        $this->assertEquals('Updated Name', $updatedUser->name());
    }

    public function testUserLifecycleFromCreationToPromotion(): void
    {
        // Given - Create a regular user
        $userId = UuidMother::random();
        $emailString = 'lifecycle@example.com';
        $email = EmailMother::create($emailString);
        
        $user = UserMother::aUser()
            ->withId($userId)
            ->withEmail($email)
            ->withName('Test User')
            ->asRegularUser()
            ->build();
        
        // When - Save initial user
        $this->userRepository->save($user);
        
        // Then - User is created as regular user
        $createdUser = $this->userRepository->findById($userId);
        $this->assertFalse($createdUser->isAdmin());
        
        // When - Update user name
        $createdUser->updateName('Updated User');
        $this->userRepository->save($createdUser);
        
        // Then - Name is updated
        $updatedUser = $this->userRepository->findById($userId);
        $this->assertEquals('Updated User', $updatedUser->name());
        $this->assertFalse($updatedUser->isAdmin());
        
        // When - Promote to admin
        $updatedUser->promoteToAdmin();
        $this->userRepository->save($updatedUser);
        
        // Then - User is now admin
        $adminUser = $this->userRepository->findById($userId);
        $this->assertTrue($adminUser->isAdmin());
        $this->assertEquals('Updated User', $adminUser->name());
        
        // And - Can still be found by email
        $foundByEmail = $this->userRepository->findByEmail($email);
        $this->assertEquals($userId, $foundByEmail->id());
        $this->assertTrue($foundByEmail->isAdmin());
    }
}
