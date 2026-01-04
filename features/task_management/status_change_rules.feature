Feature: Managing status change rules
  As a family administrator
  I want to define status change rules for tasks
  So that I can control when tasks can be assigned or completed

  Scenario: Creating a cooldown period rule after completion
    Given there is a status change rule "2 Day Cooldown" that requires 2 days cooldown after completion
    Then status change rule "2 Day Cooldown" should be active
    And there should be 1 active status change rule

  Scenario: Creating a rule requiring another task completion
    Given there is a status change rule "Previous Task Requirement" for task "Main Task" that requires another task to be completed today
    Then status change rule "Previous Task Requirement" should be active
    And there should be 1 active status change rule

  Scenario: Deactivating a status change rule
    Given there is a status change rule "Old Status Rule" that requires 3 days cooldown after completion
    When status change rule "Old Status Rule" is deactivated
    Then status change rule "Old Status Rule" should be inactive
    And there should be 0 active status change rules

  Scenario: Reactivating a status change rule
    Given there is a status change rule "Temporary Status Rule" that requires 1 day cooldown after completion
    And status change rule "Temporary Status Rule" is deactivated
    When status change rule "Temporary Status Rule" is activated
    Then status change rule "Temporary Status Rule" should be active
    And there should be 1 active status change rule

  Scenario: Managing multiple status change rules simultaneously
    Given there is a status change rule "Status Rule 1" that requires 1 day cooldown after completion
    And there is a status change rule "Status Rule 2" that requires 2 days cooldown after completion
    And there is a status change rule "Status Rule 3" for task "Special Task" that requires another task to be completed today
    Then there should be 3 active status change rules
    When status change rule "Status Rule 2" is deactivated
    Then there should be 2 active status change rules

  Scenario: Status change rules with time manipulation
    Given it is "2025-01-01 08:00:00"
    And there is a status change rule "Time Based Rule" that requires 5 days cooldown after completion
    Then status change rule "Time Based Rule" should be active
    When 3 days pass
    Then status change rule "Time Based Rule" should be active
    When 5 days pass
    And status change rule "Time Based Rule" is deactivated
    Then status change rule "Time Based Rule" should be inactive
