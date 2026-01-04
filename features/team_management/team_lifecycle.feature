Feature: Team Management
  In order to organize tasks with teams
  As a user
  I need to be able to create teams and manage team members

  Scenario: User creates a new team
    Given I am a logged-in user
    When I create a team with name "Family Team" and description "Our family household team"
    Then the team should be created successfully
    And I should be automatically added as an admin of the team

  Scenario: Admin invites a member to the team
    Given I am a logged-in user
    And I have created a team "Family Team"
    And there is a user with email "member@example.com"
    When I invite "member@example.com" to the team as a "member"
    Then an invitation should be sent to "member@example.com"
    And the user "member@example.com" should receive an email notification

  Scenario: User accepts team invitation
    Given I am a logged-in user with email "member@example.com"
    And I have been invited to team "Family Team" as a "member"
    When I accept the invitation
    Then I should be added to the team "Family Team" as a member
    And the invitation status should be "accepted"

  Scenario: Admin removes a member from the team
    Given I am a logged-in admin of team "Family Team"
    And there is a member "user@example.com" in the team
    When I remove the member "user@example.com" from the team
    Then the member should be removed from the team
    And the user should no longer have access to team resources

  Scenario: Non-admin cannot invite members
    Given I am a logged-in member of team "Family Team"
    And I am not an admin of the team
    When I try to invite "newuser@example.com" to the team
    Then I should receive an authorization error

  Scenario: Team invitation expires
    Given I am a logged-in user with email "member@example.com"
    And I have been invited to team "Family Team" 8 days ago
    When I try to accept the invitation
    Then I should receive an error that the invitation has expired

  Scenario: User can see only their teams
    Given I am a logged-in user
    And I am a member of team "Family Team"
    And there exists another team "Other Team"
    When I view my teams
    Then I should see "Family Team"
    And I should not see "Other Team"

  Scenario: Non-existing user receives invitation with registration link
    Given I am a logged-in admin of team "Family Team"
    And there is no user with email "newuser@example.com"
    When I invite "newuser@example.com" to the team
    Then an invitation should be sent to "newuser@example.com"
    And the email should contain a registration link
