---
name: api-design
description: REST API design with OpenAPI/Swagger documentation for Symfony. Use this skill when creating API endpoints, defining request/response schemas, handling authentication, or documenting APIs.
---

# API Design Skill

This skill provides guidance for designing and implementing REST APIs in the Family Plan backend using Symfony and OpenAPI documentation.

## API Documentation

Swagger UI available at: `http://localhost:8080/api/doc`

## Controller Structure

### Basic Controller

```php
declare(strict_types=1);

namespace App\Presentation\Api\TaskManagement;

use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Application\Query\GetTaskQuery;
use App\TaskManagement\Application\Query\GetTasksQuery;
use App\Shared\Domain\ValueObject\Uuid;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/tasks')]
#[OA\Tag(name: 'Tasks')]
final class TaskController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus
    ) {}

    #[Route('', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get all tasks for current user',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of tasks',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: TaskDTO::class))
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        $tasks = $this->queryBus->dispatch(new GetTasksQuery($user->getId()));

        return $this->json($tasks);
    }

    #[Route('/{id}', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get task by ID',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Task details',
                content: new OA\JsonContent(ref: new Model(type: TaskDTO::class))
            ),
            new OA\Response(response: 404, description: 'Task not found')
        ]
    )]
    public function get(string $id): JsonResponse
    {
        $task = $this->queryBus->dispatch(new GetTaskQuery($id));

        if ($task === null) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($task);
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a new task',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: CreateTaskRequest::class))
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Task created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid input'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $id = Uuid::generate()->toString();

        $this->commandBus->dispatch(new CreateTaskCommand(
            id: $id,
            name: $data['name'] ?? '',
            description: $data['description'] ?? null,
            points: $data['points'] ?? 0,
            teamId: $data['teamId']
        ));

        return $this->json(['id' => $id], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    #[OA\Put(
        summary: 'Update a task',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: UpdateTaskRequest::class))
        ),
        responses: [
            new OA\Response(response: 200, description: 'Task updated'),
            new OA\Response(response: 404, description: 'Task not found')
        ]
    )]
    public function update(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $this->commandBus->dispatch(new UpdateTaskCommand(
            id: $id,
            name: $data['name'] ?? null,
            description: $data['description'] ?? null
        ));

        return $this->json(['status' => 'updated']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Delete a task',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Task deleted'),
            new OA\Response(response: 404, description: 'Task not found')
        ]
    )]
    public function delete(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeleteTaskCommand($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
```

## Request/Response DTOs

### Request DTO

```php
declare(strict_types=1);

namespace App\Presentation\Api\TaskManagement\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: 'CreateTaskRequest',
    required: ['name', 'teamId']
)]
final readonly class CreateTaskRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 255)]
        #[OA\Property(description: 'Task name', example: 'Clean the kitchen')]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        #[OA\Property(description: 'Team ID', format: 'uuid')]
        public string $teamId,

        #[Assert\Length(max: 1000)]
        #[OA\Property(description: 'Task description', nullable: true)]
        public ?string $description = null,

        #[Assert\PositiveOrZero]
        #[OA\Property(description: 'Points for completing the task', example: 10)]
        public int $points = 0
    ) {}
}
```

### Response DTO

```php
declare(strict_types=1);

namespace App\TaskManagement\Application\Query;

use App\TaskManagement\Domain\Entity\Task;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'Task')]
final readonly class TaskDTO
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,

        #[OA\Property(example: 'Clean the kitchen')]
        public string $name,

        #[OA\Property(nullable: true)]
        public ?string $description,

        #[OA\Property(enum: ['pending', 'in_progress', 'completed', 'approved'])]
        public string $status,

        #[OA\Property(example: 10)]
        public int $points,

        #[OA\Property(format: 'uuid')]
        public string $teamId,

        #[OA\Property(format: 'uuid', nullable: true)]
        public ?string $assigneeId,

        #[OA\Property(format: 'date-time')]
        public string $createdAt,

        #[OA\Property(format: 'date-time')]
        public string $updatedAt
    ) {}

    public static function fromEntity(Task $task): self
    {
        return new self(
            id: $task->id()->toString(),
            name: $task->name(),
            description: $task->description(),
            status: $task->status()->value,
            points: $task->points(),
            teamId: $task->teamId()->toString(),
            assigneeId: $task->assigneeId()?->toString(),
            createdAt: $task->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $task->updatedAt()->format(\DateTimeInterface::ATOM)
        );
    }
}
```

## Authentication

### JWT Token Authentication

```php
// Endpoint: POST /api/auth/login
#[Route('/api/auth/login', methods: ['POST'])]
#[OA\Post(
    summary: 'Authenticate user and get JWT token',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password')
            ]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Authentication successful',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'token', type: 'string')
                ]
            )
        ),
        new OA\Response(response: 401, description: 'Invalid credentials')
    ]
)]
public function login(): JsonResponse
{
    // Handled by security firewall
}
```

### Securing Endpoints

```php
// In controller
#[IsGranted('ROLE_USER')]
public function list(): JsonResponse
{
    // Only authenticated users
}

#[IsGranted('ROLE_ADMIN')]
public function adminOnly(): JsonResponse
{
    // Only admins
}
```

## Error Handling

### Error Response Format

```php
#[OA\Schema(schema: 'Error')]
final readonly class ErrorResponse
{
    public function __construct(
        #[OA\Property(example: 'validation_error')]
        public string $code,

        #[OA\Property(example: 'Invalid input data')]
        public string $message,

        #[OA\Property(type: 'array', items: new OA\Items(
            properties: [
                new OA\Property(property: 'field', type: 'string'),
                new OA\Property(property: 'message', type: 'string')
            ]
        ), nullable: true)]
        public ?array $errors = null
    ) {}
}
```

### Exception Listener

```php
declare(strict_types=1);

namespace App\Presentation\Api\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ApiExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        $response = new JsonResponse([
            'code' => $this->getErrorCode($exception),
            'message' => $exception->getMessage()
        ], $statusCode);

        $event->setResponse($response);
    }

    private function getErrorCode(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ValidationException => 'validation_error',
            $exception instanceof NotFoundException => 'not_found',
            $exception instanceof AccessDeniedException => 'access_denied',
            default => 'internal_error'
        };
    }
}
```

## Pagination

```php
#[Route('', methods: ['GET'])]
#[OA\Get(
    parameters: [
        new OA\Parameter(
            name: 'page',
            in: 'query',
            schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
        ),
        new OA\Parameter(
            name: 'limit',
            in: 'query',
            schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100)
        )
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Paginated list',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: TaskDTO::class))),
                    new OA\Property(property: 'meta', properties: [
                        new OA\Property(property: 'page', type: 'integer'),
                        new OA\Property(property: 'limit', type: 'integer'),
                        new OA\Property(property: 'total', type: 'integer'),
                        new OA\Property(property: 'totalPages', type: 'integer')
                    ])
                ]
            )
        )
    ]
)]
public function list(Request $request): JsonResponse
{
    $page = max(1, (int) $request->query->get('page', 1));
    $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

    $result = $this->queryBus->dispatch(new GetTasksQuery($page, $limit));

    return $this->json([
        'data' => $result->items,
        'meta' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $result->total,
            'totalPages' => (int) ceil($result->total / $limit)
        ]
    ]);
}
```

## API Testing

```bash
# Test with curl
curl -X GET http://localhost:8080/api/tasks \
    -H "Authorization: Bearer <token>" \
    -H "Content-Type: application/json"

curl -X POST http://localhost:8080/api/tasks \
    -H "Authorization: Bearer <token>" \
    -H "Content-Type: application/json" \
    -d '{"name": "Test Task", "teamId": "uuid", "points": 10}'
```

## URL Patterns

| Method | URL | Description |
|--------|-----|-------------|
| GET | /api/tasks | List all tasks |
| POST | /api/tasks | Create task |
| GET | /api/tasks/{id} | Get task by ID |
| PUT | /api/tasks/{id} | Update task |
| DELETE | /api/tasks/{id} | Delete task |
| POST | /api/tasks/{id}/complete | Complete task |
| GET | /api/teams/{teamId}/tasks | List team tasks |

## Best Practices

1. **Use HTTP methods correctly** - GET for read, POST for create, PUT for update, DELETE for delete
2. **Return appropriate status codes** - 200 OK, 201 Created, 204 No Content, 400 Bad Request, 401 Unauthorized, 404 Not Found
3. **Version your API** - Use URL versioning (/api/v1/) for major changes
4. **Document everything** - OpenAPI annotations for all endpoints
5. **Validate input** - Use Symfony Validator constraints
6. **Use DTOs** - Never expose entities directly
7. **Handle errors gracefully** - Return consistent error format
