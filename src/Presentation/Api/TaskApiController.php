<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Application\Command\CompleteTaskCommand;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Application\Handler\ApproveTaskHandler;
use App\TaskManagement\Application\Handler\CompleteTaskHandler;
use App\TaskManagement\Application\Handler\CreateTaskHandler;
use App\TaskManagement\Domain\Entity\Task;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tasks', name: 'api_task_')]
class TaskApiController extends AbstractController
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly CreateTaskHandler $createTaskHandler,
        private readonly CompleteTaskHandler $completeTaskHandler,
        private readonly ApproveTaskHandler $approveTaskHandler
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $tasks = $this->taskRepository->findAll();

        return $this->json([
            'tasks' => array_map(fn(Task $task) => $this->serializeTask($task), $tasks),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
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

        $task = $this->taskRepository->findById($id);

        return $this->json($this->serializeTask($task), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $task = $this->taskRepository->findById($id);

        if (!$task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeTask($task));
    }

    #[Route('/{id}/complete', name: 'complete', methods: ['POST'])]
    public function complete(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['userId'] ?? Uuid::generate()->value();

        $command = new CompleteTaskCommand($id, $userId);
        ($this->completeTaskHandler)($command);

        $task = $this->taskRepository->findById($id);

        return $this->json($this->serializeTask($task));
    }

    #[Route('/{id}/approve', name: 'approve', methods: ['POST'])]
    public function approve(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $adminId = $data['adminId'] ?? Uuid::generate()->value();

        $command = new ApproveTaskCommand($id, $adminId);
        ($this->approveTaskHandler)($command);

        $task = $this->taskRepository->findById($id);

        return $this->json($this->serializeTask($task));
    }

    private function serializeTask(Task $task): array
    {
        return [
            'id' => $task->getId()->value(),
            'name' => $task->getName()->value(),
            'description' => $task->getDescription(),
            'points' => $task->getPoints()->value(),
            'frequency' => $task->getFrequency()->value,
            'status' => $task->getStatus()->value,
            'createdAt' => $task->getCreatedAt()->format('c'),
        ];
    }
}
