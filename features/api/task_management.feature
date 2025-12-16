Feature: Task Management
  As a family member
  I want to manage household tasks
  So that I can track and complete my responsibilities

  Scenario: Initially there are no tasks
    When I view the task list
    Then I should see an empty task list

  Scenario: Create a daily cleaning task
    When I create a task with the following details:
      | name        | Clean the kitchen            |
      | description | Wash dishes and mop floor    |
      | points      | 100                          |
      | frequency   | daily                        |
    Then the task should be created successfully
    And the task should have the name "Clean the kitchen"
    And the task should be worth "100" points
    And the task should be scheduled "daily"
    And the task should be in "pending" status

  Scenario: View a specific task
    Given I have created a task "Test Task" worth "50" points scheduled "weekly"
    When I view the task details
    Then I should see the task name "Test Task"
    And I should see it is worth "50" points
    And I should see it is scheduled "weekly"

  Scenario: Complete an assigned task
    Given I have created a task "Complete Me" worth "75" points scheduled "once"
    When I mark the task as completed
    Then the task status should be "completed"

  Scenario: Admin approves a completed task
    Given I have created a task "Approve Me" worth "60" points scheduled "monthly"
    And I have marked the task as completed
    When the admin approves the task
    Then the task status should be "approved"

  Scenario: Cannot find a non-existent task
    When I try to view a task that doesn't exist
    Then I should see a "Task not found" error
