<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use PHPUnit\Framework\Attributes\Test;

/**
 * User Management Acceptance Tests
 * 
 * These tests describe the behavior of the User Management API
 * using Given-When-Then pattern for readability
 */
class UserManagementTest extends AcceptanceTestCase
{
    /**
     * Scenario: Retrieve an empty list of users
     *   Given there are no users in the system
     *   When I request the list of users
     *   Then I should receive an empty list of users
     */
    #[Test]
    public function shouldReturnEmptyListWhenNoUsersExist(): void
    {
        // Given
        $this->givenThereAreNoUsers();
        
        // When
        $this->whenIRequestTheListOfUsers();
        
        // Then
        $this->thenTheResponseShouldBeSuccessful();
        $this->thenIShouldReceiveAnEmptyListOfUsers();
    }

    /**
     * Scenario: Create a new user
     *   Given I am an authenticated user
     *   When I create a user with valid attributes
     *   Then the user should be created successfully
     *   And the response should contain all user attributes
     */
    #[Test]
    public function shouldCreateUserWithValidAttributes(): void
    {
        // Given
        $this->givenIAmAnAuthenticatedUser();
        
        // When
        $userAttributes = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'role' => 'member',
        ];
        $this->whenICreateAUserWith($userAttributes);
        
        // Then
        $this->thenTheUserShouldBeCreatedSuccessfully();
        $this->thenTheResponseShouldContainAValidId();
        $this->thenTheResponseShouldContainUserAttributes([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'role' => 'member',
        ]);
    }

    /**
     * Scenario: Retrieve a user by their ID
     *   Given I have created a user
     *   When I request the user by their ID
     *   Then I should receive the user with correct attributes
     */
    #[Test]
    public function shouldRetrieveUserById(): void
    {
        // Given
        $userId = $this->givenIHaveCreatedAUserWith([
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'role' => 'admin',
        ]);
        
        // When
        $this->whenIRequestTheUserWithId($userId);
        
        // Then
        $this->thenTheResponseShouldBeSuccessful();
        $this->thenTheResponseShouldContainUserAttributes([
            'id' => $userId,
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'role' => 'admin',
        ]);
    }

    /**
     * Scenario: Attempt to retrieve a non-existent user
     *   When I request a user that does not exist
     *   Then I should receive a not found error
     */
    #[Test]
    public function shouldReturnNotFoundForNonExistentUser(): void
    {
        // When
        $this->whenIRequestANonExistentUser();
        
        // Then
        $this->thenIShouldReceiveANotFoundError();
    }

    /**
     * Scenario: Create multiple users and list them
     *   Given I have created multiple users
     *   When I request the list of users
     *   Then I should receive all created users
     */
    #[Test]
    public function shouldListAllCreatedUsers(): void
    {
        // Given - Create multiple users
        $this->givenIHaveCreatedAUserWith([
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'role' => 'member',
        ]);
        $this->givenIHaveCreatedAUserWith([
            'name' => 'Bob Williams',
            'email' => 'bob@example.com',
            'role' => 'member',
        ]);
        
        // When
        $this->whenIRequestTheListOfUsers();
        
        // Then
        $this->thenTheResponseShouldBeSuccessful();
        $this->assertArrayHasKey('users', $this->lastResponse);
        $this->assertCount(2, $this->lastResponse['users']);
    }

    /**
     * Scenario: Create an admin user
     *   Given I am an authenticated admin
     *   When I create a user with admin role
     *   Then the user should be created with admin privileges
     */
    #[Test]
    public function shouldCreateAdminUser(): void
    {
        // Given
        $this->givenIAmAnAuthenticatedUser();
        
        // When
        $this->whenICreateAUserWith([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
        
        // Then
        $this->thenTheUserShouldBeCreatedSuccessfully();
        $this->thenTheResponseShouldContainUserAttributes([
            'role' => 'admin',
        ]);
    }
}
