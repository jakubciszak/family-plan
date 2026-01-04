Feature: User registration with email activation
  As a new user
  I want to register for a Family Plan account
  So that I can access the application after activating my account

  Scenario: Successful user registration
    When a user registers with the following details:
      | name          | email               | password      | phoneNumber   |
      | Jan Kowalski  | jan@example.com     | password123   | +48123456789  |
    Then the user should be created successfully
    And the user "jan@example.com" should be inactive
    And an activation email should be sent to "jan@example.com"
    And the activation email should contain "Witaj w Family Plan"
    And the activation email should contain an activation link

  Scenario: User registration without phone number
    When a user registers with the following details:
      | name          | email               | password      |
      | Anna Nowak    | anna@example.com    | securePass456 |
    Then the user should be created successfully
    And the user "anna@example.com" should be inactive
    And an activation email should be sent to "anna@example.com"

  Scenario: Cannot register with duplicate email
    Given a user "existing@example.com" is already registered
    When a user registers with the following details:
      | name          | email               | password      |
      | Jan Kowalski  | existing@example.com| password123   |
    Then the registration should fail with error "User with this email already exists"
    And no activation email should be sent

  Scenario: User activates their account successfully
    Given a user has registered with email "newuser@example.com"
    When the user activates their account with the correct token
    Then the user "newuser@example.com" should be active
    And the activation token should be cleared

  Scenario: Account activation fails with invalid token
    Given a user has registered with email "testuser@example.com"
    When the user tries to activate with an invalid token
    Then the activation should fail with error "Invalid activation token"
    And the user "testuser@example.com" should remain inactive

  Scenario: Inactive user cannot login
    Given a user has registered with email "inactive@example.com"
    When the user tries to login with email "inactive@example.com" and password "password123"
    Then the login should fail with error "Your account is not activated"

  Scenario: Activated user can login
    Given a user has registered with email "active@example.com"
    And the user has activated their account
    When the user tries to login with email "active@example.com" and password "password123"
    Then the login should be successful
    And the user should have role "ROLE_USER"

  Scenario: Already activated account cannot be reactivated
    Given a user has registered with email "already-active@example.com"
    And the user has activated their account
    When the user tries to activate their account again with the same token
    Then the activation should return message "Account is already active"
