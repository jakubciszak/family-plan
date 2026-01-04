Feature: Managing bonus points rules
  As a family administrator
  I want to create bonus points rules
  So that I can encourage family members to complete tasks regularly

  Scenario: Creating and activating a monthly task count rule
    Given there is a bonus rule "Month Champion" that awards 100 bonus points for completing 20 tasks in a month
    Then bonus rule "Month Champion" should be active
    And there should be 1 active bonus rule

  Scenario: Creating a consecutive days task completion rule
    Given there is a bonus rule "5 Day Streak" that awards 50 bonus points for 5 consecutive days of task completion
    Then bonus rule "5 Day Streak" should be active
    And there should be 1 active bonus rule

  Scenario: Deactivating a bonus rule
    Given there is a bonus rule "Old Rule" that awards 30 bonus points for completing 10 tasks in a month
    When bonus rule "Old Rule" is deactivated
    Then bonus rule "Old Rule" should be inactive
    And there should be 0 active bonus rules

  Scenario: Reactivating a bonus rule
    Given there is a bonus rule "Temporary Rule" that awards 40 bonus points for completing 15 tasks in a month
    And bonus rule "Temporary Rule" is deactivated
    When bonus rule "Temporary Rule" is activated
    Then bonus rule "Temporary Rule" should be active
    And there should be 1 active bonus rule

  Scenario: Managing multiple bonus rules simultaneously
    Given there is a bonus rule "Rule 1" that awards 50 bonus points for completing 10 tasks in a month
    And there is a bonus rule "Rule 2" that awards 75 bonus points for completing 15 tasks in a month
    And there is a bonus rule "Rule 3" that awards 100 bonus points for 7 consecutive days of task completion
    Then there should be 3 active bonus rules
    When bonus rule "Rule 2" is deactivated
    Then there should be 2 active bonus rules
    And all bonus rules should be listed

  Scenario: Bonus rules with time manipulation
    Given it is "2025-01-01 08:00:00"
    And there is a bonus rule "New Year Bonus" that awards 200 bonus points for completing 25 tasks in a month
    Then bonus rule "New Year Bonus" should be active
    When 15 days pass
    Then bonus rule "New Year Bonus" should be active
    When 20 days pass
    And bonus rule "New Year Bonus" is deactivated
    Then bonus rule "New Year Bonus" should be inactive
