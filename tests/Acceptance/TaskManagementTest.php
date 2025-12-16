<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use PHPUnit\Framework\Attributes\Test;

/**
 * Task Management Acceptance Tests
 * 
 * These tests describe the behavior of the Task Management API
 * using Given-When-Then pattern for readability
 */
class TaskManagementTest extends AcceptanceTestCase
{
    /**
     * Scenario: Retrieve an empty list of tasks
     *   Given there are no tasks in the system
     *   When I request the list of tasks
     *   Then I should receive an empty list of tasks
     */
    #[Test]
    public function shouldReturnEmptyListWhenNoTasksExist(): void
    {
        // Given
        $this->givenThereAreNoTasks();
        
        // When
        $this->whenIRequestTheListOfTasks();
        
        // Then
        $this->thenTheResponseShouldBeSuccessful();
        $this->thenIShouldReceiveAnEmptyListOfTasks();
    }

    /**
     * Scenario: Create a new task
     *   Given I am an authenticated user
     *   When I create a task with valid attributes
     *   Then the task should be created successfully
     *   And the response should contain all task attributes
     */
    #[Test]
    public function shouldCreateTaskWithValidAttributes(): void
    {
        // Given
        $this->givenIAmAnAuthenticatedUser();
        
        // When
        $taskAttributes = [
            'name' => 'Clean the kitchen',
            'description' => 'Wash dishes and wipe counters',
            'points' => 100,
            'frequency' => 'daily',
        ];
        $this->whenICreateATaskWith($taskAttributes);
        
        // Then
        $this->thenTheTaskShouldBeCreatedSuccessfully();
        $this->thenTheResponseShouldContainAValidId();
        $this->thenTheResponseShouldContainTaskAttributes([
            'name' => 'Clean the kitchen',
            'description' => 'Wash dishes and wipe counters',
            'points' => 100,
            'frequency' => 'daily',
        ]);
    }

    /**
     * Scenario: Retrieve a task by its ID
     *   Given I have created a task
     *   When I request the task by its ID
     *   Then I should receive the task with correct attributes
     */
    #[Test]
    public function shouldRetrieveTaskById(): void
    {
        // Given
        $taskId = $this->givenIHaveCreatedATaskWith([
            'name' => 'Take out trash',
            'description' => 'Empty all trash bins',
            'points' => 50,
            'frequency' => 'weekly',
        ]);
        
        // When
        $this->whenIRequestTheTaskWithId($taskId);
        
        // Then
        $this->thenTheResponseShouldBeSuccessful();
        $this->thenTheResponseShouldContainTaskAttributes([
            'id' => $taskId,
            'name' => 'Take out trash',
            'description' => 'Empty all trash bins',
            'points' => 50,
            'frequency' => 'weekly',
        ]);
    }

    /**
     * Scenario: Complete a task
     *   Given I have created a task
     *   When I mark the task as complete
     *   Then the task status should be "completed"
     */
    #[Test]
    public function shouldMarkTaskAsCompleted(): void
    {
        // Given
        $taskId = $this->givenIHaveCreatedATaskWith([
            'name' => 'Vacuum living room',
            'description' => 'Vacuum the entire living room',
            'points' => 75,
            'frequency' => 'once',
        ]);
        
        // When
        $this->whenICompleteTheTaskWithId($taskId);
        
        // Then
        $this->thenTheResponseShouldBeSuccessful();
        $this->thenTheTaskShouldHaveStatus('completed');
    }

    /**
     * Scenario: Approve a completed task
     *   Given I have created and completed a task
     *   When I approve the task
     *   Then the task status should be "approved"
     */
    #[Test]
    public function shouldApproveCompletedTask(): void
    {
        // Given - Create and complete a task
        $taskId = $this->givenIHaveCreatedATaskWith([
            'name' => 'Mow the lawn',
            'description' => 'Cut grass in the backyard',
            'points' => 150,
            'frequency' => 'monthly',
        ]);
        $this->whenICompleteTheTaskWithId($taskId);
        
        // When
        $this->whenIApproveTheTaskWithId($taskId);
        
        // Then
        $this->thenTheResponseShouldBeSuccessful();
        $this->thenTheTaskShouldHaveStatus('approved');
    }

    /**
     * Scenario: Attempt to retrieve a non-existent task
     *   When I request a task that does not exist
     *   Then I should receive a not found error
     */
    #[Test]
    public function shouldReturnNotFoundForNonExistentTask(): void
    {
        // When
        $this->whenIRequestANonExistentTask();
        
        // Then
        $this->thenIShouldReceiveANotFoundError();
    }

    /**
     * Scenario: Create multiple tasks and list them
     *   Given I have created multiple tasks
     *   When I request the list of tasks
     *   Then I should receive all created tasks
     */
    #[Test]
    public function shouldListAllCreatedTasks(): void
    {
        // Given - Create multiple tasks
        $this->givenIHaveCreatedATaskWith([
            'name' => 'Task 1',
            'description' => 'First task',
            'points' => 10,
            'frequency' => 'daily',
        ]);
        $this->givenIHaveCreatedATaskWith([
            'name' => 'Task 2',
            'description' => 'Second task',
            'points' => 20,
            'frequency' => 'weekly',
        ]);
        
        // When
        $this->whenIRequestTheListOfTasks();
        
        // Then
        $this->thenTheResponseShouldBeSuccessful();
        $this->assertArrayHasKey('tasks', $this->lastResponse);
        $this->assertCount(2, $this->lastResponse['tasks']);
    }
}
