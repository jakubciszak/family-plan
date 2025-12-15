<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Application\Command\CompleteTaskCommand;
use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Application\Handler\CreateTaskHandler;
use App\TaskManagement\Application\Handler\CompleteTaskHandler;
use App\TaskManagement\Application\Handler\ApproveTaskHandler;
use App\TaskManagement\Domain\Repository\TaskRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tasks')]
class TaskController extends AbstractController
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private CreateTaskHandler $createTaskHandler,
        private CompleteTaskHandler $completeTaskHandler,
        private ApproveTaskHandler $approveTaskHandler
    ) {
    }

    #[Route('/', name: 'app_task_list', methods: ['GET'])]
    public function list(): Response
    {
        $tasks = $this->taskRepository->findAll();

        return $this->render('task/list.html.twig', [
            'tasks' => $tasks,
        ]);
    }

    #[Route('/create', name: 'app_task_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $command = new CreateTaskCommand(
                Uuid::generate()->value(),
                $request->request->get('name'),
                $request->request->get('description'),
                (int) $request->request->get('points'),
                $request->request->get('frequency')
            );

            ($this->createTaskHandler)($command);

            return $this->redirectToRoute('app_task_list');
        }

        return $this->render('task/create.html.twig');
    }

    #[Route('/{id}/complete', name: 'app_task_complete', methods: ['POST'])]
    public function complete(string $id): Response
    {
        // In real app, get user ID from security context
        $userId = Uuid::generate()->value();
        
        $command = new CompleteTaskCommand($id, $userId);
        ($this->completeTaskHandler)($command);

        return $this->redirectToRoute('app_task_list');
    }

    #[Route('/{id}/approve', name: 'app_task_approve', methods: ['POST'])]
    public function approve(string $id): Response
    {
        // In real app, get admin ID from security context
        $adminId = Uuid::generate()->value();
        
        $command = new ApproveTaskCommand($id, $adminId);
        ($this->approveTaskHandler)($command);

        return $this->redirectToRoute('app_task_list');
    }
}
