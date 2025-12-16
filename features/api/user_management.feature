Feature: User Management
  As an administrator
  I want to manage family member accounts
  So that everyone can access the system

  Scenario: Initially there are no users
    When I view the user list
    Then I should see an empty user list

  Scenario: Create a new family member account
    When I create a user account with the following details:
      | name     | John Doe             |
      | email    | john@example.com     |
      | password | securePassword123    |
      | role     | ROLE_USER            |
    Then the user account should be created successfully
    And the user should have the name "John Doe"
    And the user should have the email "john@example.com"
    And the user should have the role "ROLE_USER"

  Scenario: Create an administrator account
    When I create a user account with the following details:
      | name     | Admin User           |
      | email    | admin@example.com    |
      | password | adminPassword123     |
      | role     | ROLE_ADMIN           |
    Then the user account should be created successfully
    And the user should have the role "ROLE_ADMIN"

  Scenario: View a specific user account
    Given I have created a user "Jane Smith" with email "jane@example.com"
    When I view the user account details
    Then I should see the user name "Jane Smith"
    And I should see the email "jane@example.com"

  Scenario: Cannot find a non-existent user
    When I try to view a user account that doesn't exist
    Then I should see a "User not found" error
