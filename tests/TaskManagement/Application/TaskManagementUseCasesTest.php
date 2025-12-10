<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Application;

use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Application\Command\CompleteTaskCommand;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Application\Handler\ApproveTaskHandler;
use App\TaskManagement\Application\Handler\CompleteTaskHandler;
use App\TaskManagement\Application\Handler\CreateTaskHandler;
use App\TaskManagement\Infrastructure\Persistence\InMemoryTaskRepository;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\TaskManagement\Assert\TaskAssert;
use App\Tests\TaskManagement\Mother\FrequencyMother;
use App\Tests\TaskManagement\Mother\PointsMother;
use App\Tests\TaskManagement\Mother\TaskNameMother;
use PHPUnit\Framework\TestCase;

/**
 * Detroit school test for Task Management use cases
 * Tests the complete workflow with real handlers and repository
 */
class TaskManagementUseCasesTest extends TestCase
{
    private InMemoryTaskRepository $repository;
    private CreateTaskHandler $createHandler;
    private CompleteTaskHandler $completeHandler;
    private ApproveTaskHandler $approveHandler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryTaskRepository();
        $this->createHandler = new CreateTaskHandler($this->repository);
        $this->completeHandler = new CompleteTaskHandler($this->repository);
        $this->approveHandler = new ApproveTaskHandler($this->repository);
    }

    public function testCreateTaskUseCaseCreatesAndPersistsTask(): void
    {
        // Given
        $taskId = UuidMother::random();
        $name = TaskNameMother::create('Clean kitchen');
        $description = 'Wash dishes and wipe counters';
        $points = PointsMother::medium();
        $frequency = FrequencyMother::daily();

        $command = new CreateTaskCommand(
            $taskId,
            $name,
            $description,
            $points,
            $frequency,
            null
        );

        // When
        ($this->createHandler)($command);

        // Then
        $persistedTask = $this->repository->findById($taskId);
        $this->assertNotNull($persistedTask);
        TaskAssert::assertTaskHasId($taskId, $persistedTask);
        TaskAssert::assertTaskHasName($name, $persistedTask);
        TaskAssert::assertTaskHasDescription($description, $persistedTask);
        TaskAssert::assertTaskHasPoints($points, $persistedTask);
        TaskAssert::assertTaskHasFrequency($frequency, $persistedTask);
        TaskAssert::assertTaskIsPending($persistedTask);
    }

    public function testCompleteTaskUseCaseMarksTaskAsCompleted(): void
    {
        // Given - Create a task first
        $taskId = UuidMother::random();
        $userId = UuidMother::random();

        $createCommand = new CreateTaskCommand(
            $taskId,
            TaskNameMother::create(),
            'Task description',
            PointsMother::medium(),
            FrequencyMother::daily(),
            null
        );
        ($this->createHandler)($createCommand);

        $completeCommand = new CompleteTaskCommand($taskId, $userId);

        // When
        ($this->completeHandler)($completeCommand);

        // Then
        $completedTask = $this->repository->findById($taskId);
        $this->assertNotNull($completedTask);
        TaskAssert::assertTaskIsCompleted($completedTask);
        TaskAssert::assertTaskWasCompletedAt($completedTask);
    }

    public function testApproveTaskUseCaseApprovesCompletedTask(): void
    {
        // Given - Create and complete a task first
        $taskId = UuidMother::random();
        $userId = UuidMother::random();
        $adminId = UuidMother::random();

        $createCommand = new CreateTaskCommand(
            $taskId,
            TaskNameMother::create(),
            'Task description',
            PointsMother::medium(),
            FrequencyMother::daily(),
            null
        );
        ($this->createHandler)($createCommand);

        $completeCommand = new CompleteTaskCommand($taskId, $userId);
        ($this->completeHandler)($completeCommand);

        $approveCommand = new ApproveTaskCommand($taskId, $adminId);

        // When
        ($this->approveHandler)($approveCommand);

        // Then
        $approvedTask = $this->repository->findById($taskId);
        $this->assertNotNull($approvedTask);
        TaskAssert::assertTaskIsApproved($approvedTask);
        TaskAssert::assertTaskWasApprovedAt($approvedTask);
    }

    public function testCompleteWorkflowFromCreationToApproval(): void
    {
        // Given
        $taskId = UuidMother::random();
        $userId = UuidMother::random();
        $adminId = UuidMother::random();

        // When - Create task
        $createCommand = new CreateTaskCommand(
            $taskId,
            TaskNameMother::create('Important task'),
            'This needs to be done',
            PointsMother::high(),
            FrequencyMother::weekly(),
            $userId
        );
        ($this->createHandler)($createCommand);

        // Then - Task is created and pending
        $task = $this->repository->findById($taskId);
        TaskAssert::assertTaskIsPending($task);
        TaskAssert::assertTaskIsAssignedTo($userId, $task);

        // When - User completes the task
        $completeCommand = new CompleteTaskCommand($taskId, $userId);
        ($this->completeHandler)($completeCommand);

        // Then - Task is completed
        $task = $this->repository->findById($taskId);
        TaskAssert::assertTaskIsCompleted($task);

        // When - Admin approves the task
        $approveCommand = new ApproveTaskCommand($taskId, $adminId);
        ($this->approveHandler)($approveCommand);

        // Then - Task is approved
        $task = $this->repository->findById($taskId);
        TaskAssert::assertTaskIsApproved($task);
    }

    public function testRepositoryCanFindPendingTasks(): void
    {
        // Given - Create multiple tasks with different statuses
        $pendingTaskId = UuidMother::random();
        $completedTaskId = UuidMother::random();

        $pendingCommand = new CreateTaskCommand(
            $pendingTaskId,
            TaskNameMother::create(),
            'Pending task',
            PointsMother::medium(),
            FrequencyMother::daily(),
            null
        );
        ($this->createHandler)($pendingCommand);

        $completedCommand = new CreateTaskCommand(
            $completedTaskId,
            TaskNameMother::create(),
            'To be completed',
            PointsMother::medium(),
            FrequencyMother::daily(),
            null
        );
        ($this->createHandler)($completedCommand);
        ($this->completeHandler)(new CompleteTaskCommand($completedTaskId, UuidMother::random()));

        // When
        $pendingTasks = $this->repository->findPending();

        // Then
        $this->assertCount(1, $pendingTasks);
        TaskAssert::assertTaskHasId($pendingTaskId, $pendingTasks[0]);
    }

    public function testRepositoryCanFindCompletedTasks(): void
    {
        // Given
        $taskId = UuidMother::random();
        $createCommand = new CreateTaskCommand(
            $taskId,
            TaskNameMother::create(),
            'Task',
            PointsMother::medium(),
            FrequencyMother::daily(),
            null
        );
        ($this->createHandler)($createCommand);
        ($this->completeHandler)(new CompleteTaskCommand($taskId, UuidMother::random()));

        // When
        $completedTasks = $this->repository->findCompleted();

        // Then
        $this->assertCount(1, $completedTasks);
        TaskAssert::assertTaskIsCompleted($completedTasks[0]);
    }

    public function testRepositoryCanFindTasksByAssignedUser(): void
    {
        // Given
        $userId = UuidMother::random();
        $taskId1 = UuidMother::random();
        $taskId2 = UuidMother::random();

        $command1 = new CreateTaskCommand(
            $taskId1,
            TaskNameMother::create(),
            'Task 1',
            PointsMother::medium(),
            FrequencyMother::daily(),
            $userId
        );
        ($this->createHandler)($command1);

        $command2 = new CreateTaskCommand(
            $taskId2,
            TaskNameMother::create(),
            'Task 2',
            PointsMother::medium(),
            FrequencyMother::daily(),
            $userId
        );
        ($this->createHandler)($command2);

        $command3 = new CreateTaskCommand(
            UuidMother::random(),
            TaskNameMother::create(),
            'Task 3',
            PointsMother::medium(),
            FrequencyMother::daily(),
            UuidMother::random() // Different user
        );
        ($this->createHandler)($command3);

        // When
        $userTasks = $this->repository->findByAssignedUser($userId);

        // Then
        $this->assertCount(2, $userTasks);
    }
}
