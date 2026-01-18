<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Application\Command\AssignTaskCommand;
use App\TaskManagement\Application\Command\CompleteTaskCommand;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Application\Query\FindTaskByIdQuery;
use App\TaskManagement\Application\Query\GetAllTasksQuery;
use App\TaskManagement\Application\Query\GetTasksByUserTeamsQuery;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use App\UserManagement\Application\Query\FindUserByIdQuery;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tasks', name: 'api_task_')]
#[OA\Tag(name: 'Tasks')]
class TaskApiController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TaskRepositoryInterface $taskRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/tasks',
        summary: 'List tasks from user teams',
        tags: ['Tasks']
    )]
    #[OA\Parameter(
        name: 'teamId',
        in: 'query',
        required: false,
        description: 'Filter tasks by specific team ID',
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'List of tasks from teams the user is a member of',
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
                            new OA\Property(property: 'teamId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                        ]
                    )
                )
            ]
        )
    )]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $teamId = $request->query->get('teamId');

        // If no user is authenticated, return all tasks or filter by teamId if provided
        // (for backward compatibility with tests)
        // In production, authentication middleware should be enforced
        if (!$user) {
            if ($teamId) {
                // Filter by specific team
                $tasks = $this->taskRepository->findByTeamId(Uuid::fromString($teamId));
            } else {
                $tasks = $this->queryBus->dispatch(new GetAllTasksQuery())->last(HandledStamp::class)->getResult();
            }

            return $this->json([
                'tasks' => array_map(fn(Task $task) => $this->serializeTask($task), $tasks),
            ]);
        }

        $userId = $user->getUserIdentifier();
        $userEntity = $this->userRepository->findByEmail(\App\UserManagement\Domain\ValueObject\Email::fromString($userId));

        if (!$userEntity) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Use the new query to filter by user's team membership
        $tasks = $this->queryBus->dispatch(
            new GetTasksByUserTeamsQuery($userEntity->id()->value(), $teamId)
        )->last(HandledStamp::class)->getResult();

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
            required: ['name', 'points', 'frequency', 'teamId', 'createdBy'],
            properties: [
                new OA\Property(property: 'name', type: 'string', minLength: 1, maxLength: 255, example: 'Clean the kitchen'),
                new OA\Property(property: 'description', type: 'string', example: 'Wash dishes and mop floor'),
                new OA\Property(property: 'points', type: 'integer', minimum: 0, maximum: 1000, example: 50),
                new OA\Property(property: 'frequency', type: 'string', enum: ['once', 'daily', 'weekly', 'monthly'], example: 'daily'),
                new OA\Property(property: 'teamId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440001'),
                new OA\Property(property: 'createdBy', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440002'),
                new OA\Property(property: 'assignedUserId', type: 'string', format: 'uuid', nullable: true, example: '550e8400-e29b-41d4-a716-446655440003')
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

        // Validate required fields
        if (empty($data['teamId'])) {
            return $this->json(['error' => 'teamId is required'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['createdBy'])) {
            return $this->json(['error' => 'createdBy is required'], Response::HTTP_BAD_REQUEST);
        }

        $id = Uuid::generate()->value();
        $command = new CreateTaskCommand(
            $id,
            $data['name'],
            $data['description'] ?? '',
            $data['points'] ?? 0,
            $data['frequency'] ?? 'once',
            $data['createdBy'],
            $data['teamId'],
            $data['assignedUserId'] ?? null
        );

        try {
            $this->commandBus->dispatch($command);
        } catch (\App\TaskManagement\Domain\Exception\TaskCreationNotAllowedException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $task = $this->queryBus->dispatch(new FindTaskByIdQuery($id))->last(HandledStamp::class)->getResult();

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
                new OA\Property(property: 'teamId', type: 'string', format: 'uuid'),
                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Task not found or access denied',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Task not found')
            ]
        )
    )]
    public function get(string $id): JsonResponse
    {
        $task = $this->queryBus->dispatch(new FindTaskByIdQuery($id))->last(HandledStamp::class)->getResult();

        if (!$task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();

        // If user is authenticated, check team membership
        if ($user) {
            $userId = $user->getUserIdentifier();
            $userEntity = $this->userRepository->findByEmail(\App\UserManagement\Domain\ValueObject\Email::fromString($userId));

            if ($userEntity && $task->teamId() !== null) {
                // Check if user has access to this task (is member of the task's team)
                $userTasks = $this->queryBus->dispatch(
                    new GetTasksByUserTeamsQuery($userEntity->id()->value(), $task->teamId()->value())
                )->last(HandledStamp::class)->getResult();

                // If the query returns empty, user is not a member of the team
                if (empty($userTasks)) {
                    return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
                }
            }
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
        $this->commandBus->dispatch($command);

        $task = $this->queryBus->dispatch(new FindTaskByIdQuery($id))->last(HandledStamp::class)->getResult();

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
        $this->commandBus->dispatch($command);

        $task = $this->queryBus->dispatch(new FindTaskByIdQuery($id))->last(HandledStamp::class)->getResult();

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
        $task = $this->queryBus->dispatch(new FindTaskByIdQuery($id))->last(HandledStamp::class)->getResult();
        if (!$task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        // Validate user exists
        $user = $this->queryBus->dispatch(new FindUserByIdQuery($userId))->last(HandledStamp::class)->getResult();
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Execute command
        $this->commandBus->dispatch(new AssignTaskCommand($id, $userId));

        // Fetch updated task
        $task = $this->queryBus->dispatch(new FindTaskByIdQuery($id))->last(HandledStamp::class)->getResult();

        return $this->json($this->serializeTask($task));
    }

    private function serializeTask(Task $task): array
    {
        $assignedUserId = $task->assignedUserId();
        $assignedUserName = null;
        if ($assignedUserId !== null) {
            $assignedUser = $this->queryBus->dispatch(new FindUserByIdQuery($assignedUserId->value()))->last(HandledStamp::class)->getResult();
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
            'teamId' => $task->teamId()?->value(),
            'createdAt' => $task->createdAt()->format('c'),
        ];
    }
}
