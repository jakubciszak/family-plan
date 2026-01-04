Feature: Complete task lifecycle
  As a family administrator
  I want to manage household tasks
  So that family members can complete tasks and earn points

  Scenario: Creating, assigning, completing and approving a task
    Given there is an administrator "Mom"
    And there is a family member "John"
    And "Mom" creates a task "Wash dishes" worth 50 points
    When "Mom" assigns task "Wash dishes" to "John"
    Then task "Wash dishes" should have status "new"
    And "John" should have no wallet yet

    When "John" completes task "Wash dishes"
    Then task "Wash dishes" should have status "completed"
    And "John" should have no wallet yet

    When "Mom" approves task "Wash dishes"
    Then task "Wash dishes" should have status "approved"
    And "John" should have 50 points

  Scenario: Multiple family members complete tasks independently
    Given there is an administrator "Dad"
    And there are the following family members:
      | name    |
      | Anna    |
      | Bob     |
      | Charlie |
    And the following tasks have been created:
      | task           | points | description                |
      | Vacuum house   | 40     | Vacuum the entire house    |
      | Take out trash | 30     | Take trash to the bins     |
      | Water plants   | 20     | Water all the plants       |

    When "Dad" assigns task "Vacuum house" to "Anna"
    And "Dad" assigns task "Take out trash" to "Bob"
    And "Dad" assigns task "Water plants" to "Charlie"
    And "Anna" completes task "Vacuum house"
    And "Bob" completes task "Take out trash"
    And "Charlie" completes task "Water plants"
    And "Dad" approves task "Vacuum house"
    And "Dad" approves task "Take out trash"
    And "Dad" approves task "Water plants"

    Then "Anna" should have 40 points
    And "Bob" should have 30 points
    And "Charlie" should have 20 points

  Scenario: Only administrators can approve tasks
    Given there is an administrator "Mom"
    And there is a family member "John"
    And there is a family member "Kate"
    And "Mom" creates a task "Clean room" worth 60 points
    And "Mom" assigns task "Clean room" to "John"
    And "John" completes task "Clean room"

    When "Kate" approves task "Clean room"
    Then the operation should fail with "Only administrators can approve tasks"

  Scenario: Accumulating points from multiple tasks
    Given it is "2025-01-01 08:00:00"
    And there is an administrator "Dad"
    And there is a family member "Anna"
    And "Dad" creates a task "Task 1" worth 100 points
    And "Dad" creates a task "Task 2" worth 150 points
    And "Dad" creates a task "Task 3" worth 200 points

    When "Dad" assigns task "Task 1" to "Anna"
    And "Anna" completes task "Task 1"
    And "Dad" approves task "Task 1"
    Then "Anna" should have 100 points

    When 1 day passes
    And "Dad" assigns task "Task 2" to "Anna"
    And "Anna" completes task "Task 2"
    And "Dad" approves task "Task 2"
    Then "Anna" should have 250 points

    When 2 days pass
    And "Dad" assigns task "Task 3" to "Anna"
    And "Anna" completes task "Task 3"
    And "Dad" approves task "Task 3"
    Then "Anna" should have 450 points
