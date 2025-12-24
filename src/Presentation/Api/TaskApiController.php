<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Application\Command\AssignTaskCommand;
use App\TaskManagement\Application\Command\CompleteTaskCommand;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Application\Handler\ApproveTaskHandler;
use App\TaskManagement\Application\Handler\AssignTaskHandler;
use App\TaskManagement\Application\Handler\CompleteTaskHandler;
use App\TaskManagement\Application\Handler\CreateTaskHandler;
use App\TaskManagement\Application\Query\FindTaskByIdQuery;
use App\TaskManagement\Application\Query\FindTaskByIdQueryHandler;
use App\TaskManagement\Application\Query\GetAllTasksQuery;
use App\TaskManagement\Application\Query\GetAllTasksQueryHandler;
use App\TaskManagement\Domain\Entity\Task;
use App\UserManagement\Application\Query\FindUserByIdQuery;
use App\UserManagement\Application\Query\FindUserByIdQueryHandler;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tasks', name: 'api_task_')]
#[OA\Tag(name: 'Tasks')]
class TaskApiController extends AbstractController
{
    public function __construct(
        private readonly CreateTaskHandler $createTaskHandler,
        private readonly CompleteTaskHandler $completeTaskHandler,
        private readonly ApproveTaskHandler $approveTaskHandler,
        private readonly AssignTaskHandler $assignTaskHandler,
        private readonly GetAllTasksQueryHandler $getAllTasksQueryHandler,
        private readonly FindTaskByIdQueryHandler $findTaskByIdQueryHandler,
        private readonly FindUserByIdQueryHandler $findUserByIdQueryHandler
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/tasks',
        summary: 'List all tasks',
        tags: ['Tasks']
    )]
    #[OA\Response(
        response: 200,
        description: 'List of tasks',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'tasks',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'points', type: 'integer'),
                            new OA\Property(property: 'frequency', type: 'string', enum: ['once', 'daily', 'weekly', 'monthly']),
                            new OA\Property(property: 'status', type: 'string', enum: ['pending', 'completed', 'approved']),
                            new OA\Property(property: 'assignedUserId', type: 'string', format: 'uuid', nullable: true),
                            new OA\Property(property: 'assignedUserName', type: 'string', nullable: true),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                        ]
                    )
                )
            ]
        )
    )]
    public function list(): JsonResponse
    {
        $tasks = ($this->getAllTasksQueryHandler)(new GetAllTasksQuery());

        return $this->json([
            'tasks' => array_map(fn(Task $task) => $this->serializeTask($task), $tasks),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/tasks',
        summary: 'Create a new task',
        tags: ['Tasks']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'points', 'frequency'],
            properties: [
                new OA\Property(property: 'name', type: 'string', minLength: 1, maxLength: 255, example: 'Clean the kitchen'),
                new OA\Property(property: 'description', type: 'string', example: 'Wash dishes and mop floor'),
                new OA\Property(property: 'points', type: 'integer', minimum: 0, maximum: 1000, example: 50),
                new OA\Property(property: 'frequency', type: 'string', enum: ['once', 'daily', 'weekly', 'monthly'], example: 'daily')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Task created successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'points', type: 'integer'),
                new OA\Property(property: 'frequency', type: 'string'),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
            ]
        )
    )]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $id = Uuid::generate()->value();
        $command = new CreateTaskCommand(
            $id,
            $data['name'],
            $data['description'] ?? '',
            $data['points'] ?? 0,
            $data['frequency'] ?? 'once'
        );

        ($this->createTaskHandler)($command);

        $task = ($this->findTaskByIdQueryHandler)(new FindTaskByIdQuery($id));

        return $this->json($this->serializeTask($task), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/tasks/{id}',
        summary: 'Get task by ID',
        tags: ['Tasks']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Task UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Task details',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'points', type: 'integer'),
                new OA\Property(property: 'frequency', type: 'string'),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Task not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Task not found')
            ]
        )
    )]
    public function get(string $id): JsonResponse
    {
        $task = ($this->findTaskByIdQueryHandler)(new FindTaskByIdQuery($id));

        if (!$task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeTask($task));
    }

    #[Route('/{id}/complete', name: 'complete', methods: ['POST'])]
    #[OA\Post(
        path: '/api/tasks/{id}/complete',
        summary: 'Complete a task',
        tags: ['Tasks']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Task UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['userId'],
            properties: [
                new OA\Property(property: 'userId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440002')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Task completed successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'points', type: 'integer'),
                new OA\Property(property: 'frequency', type: 'string'),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
            ]
        )
    )]
    public function complete(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['userId'] ?? Uuid::generate()->value();

        $command = new CompleteTaskCommand($id, $userId);
        ($this->completeTaskHandler)($command);

        $task = ($this->findTaskByIdQueryHandler)(new FindTaskByIdQuery($id));

        return $this->json($this->serializeTask($task));
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    #[OA\Post(
        path: '/api/tasks/{id}/approve',
        summary: 'Approve a completed task',
        tags: ['Tasks']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Task UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['adminId'],
            properties: [
                new OA\Property(property: 'adminId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440003')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Task approved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'points', type: 'integer'),
                new OA\Property(property: 'frequency', type: 'string'),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
            ]
        )
    )]
    public function approve(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $adminId = $data['adminId'] ?? Uuid::generate()->value();

        $command = new ApproveTaskCommand($id, $adminId);
        ($this->approveTaskHandler)($command);

        $task = ($this->findTaskByIdQueryHandler)(new FindTaskByIdQuery($id));

        return $this->json($this->serializeTask($task));
    }

    #[Route('/{id}/assign', name: 'assign', methods: ['POST'])]
    #[OA\Post(
        path: '/api/tasks/{id}/assign',
        summary: 'Assign a task to a user',
        tags: ['Tasks']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Task UUID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['userId'],
            properties: [
                new OA\Property(property: 'userId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440002')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Task assigned successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'points', type: 'integer'),
                new OA\Property(property: 'frequency', type: 'string'),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'assignedUserId', type: 'string', format: 'uuid'),
                new OA\Property(property: 'assignedUserName', type: 'string'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Task or user not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Task not found')
            ]
        )
    )]
    public function assign(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['userId'] ?? null;

        if (!$userId) {
            return $this->json(['error' => 'userId is required'], Response::HTTP_BAD_REQUEST);
        }

        // Validate task exists
        $task = ($this->findTaskByIdQueryHandler)(new FindTaskByIdQuery($id));
        if (!$task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        // Validate user exists
        $user = ($this->findUserByIdQueryHandler)(new FindUserByIdQuery($userId));
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Execute command
        ($this->assignTaskHandler)(new AssignTaskCommand($id, $userId));

        // Fetch updated task
        $task = ($this->findTaskByIdQueryHandler)(new FindTaskByIdQuery($id));

        return $this->json($this->serializeTask($task));
    }

    private function serializeTask(Task $task): array
    {
        $assignedUserId = $task->assignedUserId();
        $assignedUserName = null;
        if ($assignedUserId !== null) {
            $assignedUser = ($this->findUserByIdQueryHandler)(new FindUserByIdQuery($assignedUserId->value()));
            if ($assignedUser !== null) {
                $assignedUserName = $assignedUser->name();
            }
        }

        return [
            'id' => $task->id()->value(),
            'name' => $task->name()->value(),
            'description' => $task->description(),
            'points' => $task->points()->value(),
            'frequency' => $task->frequency()->value,
            'status' => $task->status()->value,
            'assignedUserId' => $assignedUserId?->value(),
            'assignedUserName' => $assignedUserName,
            'createdAt' => $task->createdAt()->format('c'),
        ];
    }
}
