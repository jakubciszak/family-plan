<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\CreateTaskExecutionCommand;
use App\TaskManagement\Application\Command\CompleteTaskExecutionCommand;
use App\TaskManagement\Application\Command\ApproveTaskExecutionCommand;
use App\TaskManagement\Application\Handler\CreateTaskExecutionHandler;
use App\TaskManagement\Application\Handler\CompleteTaskExecutionHandler;
use App\TaskManagement\Application\Handler\ApproveTaskExecutionHandler;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Repository\RoutineTaskRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/task-executions')]
class TaskExecutionController extends AbstractController
{
    public function __construct(
        private TaskExecutionRepositoryInterface $taskExecutionRepository,
        private RoutineTaskRepositoryInterface $routineTaskRepository,
        private CreateTaskExecutionHandler $createTaskExecutionHandler,
        private CompleteTaskExecutionHandler $completeTaskExecutionHandler,
        private ApproveTaskExecutionHandler $approveTaskExecutionHandler
    ) {
    }

    #[Route('/', name: 'app_task_execution_list', methods: ['GET'])]
    public function list(): Response
    {
        $executions = $this->taskExecutionRepository->findAll();

        return $this->render('task_execution/list.html.twig', [
            'executions' => $executions,
        ]);
    }

    #[Route('/pending', name: 'app_task_execution_pending', methods: ['GET'])]
    public function pending(): Response
    {
        $executions = $this->taskExecutionRepository->findPending();

        return $this->render('task_execution/list.html.twig', [
            'executions' => $executions,
        ]);
    }

    #[Route('/completed', name: 'app_task_execution_completed', methods: ['GET'])]
    public function completed(): Response
    {
        $executions = $this->taskExecutionRepository->findCompleted();

        return $this->render('task_execution/list.html.twig', [
            'executions' => $executions,
        ]);
    }

    #[Route('/today', name: 'app_task_execution_today', methods: ['GET'])]
    public function today(): Response
    {
        $executions = $this->taskExecutionRepository->findScheduledForDate(new \DateTimeImmutable());

        return $this->render('task_execution/list.html.twig', [
            'executions' => $executions,
            'title' => 'Today\'s Tasks',
        ]);
    }

    #[Route('/create', name: 'app_task_execution_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $routineTaskId = $request->request->get('routine_task_id');
            
            $command = new CreateTaskExecutionCommand(
                Uuid::generate()->value(),
                $routineTaskId ?: null,
                $request->request->get('name'),
                $request->request->get('description'),
                $request->request->get('points') ? (int) $request->request->get('points') : null,
                $request->request->get('scheduled_for') ?: (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            );

            ($this->createTaskExecutionHandler)($command);

            $this->addFlash('success', 'Task execution created successfully!');
            return $this->redirectToRoute('app_task_execution_list');
        }

        $routineTasks = $this->routineTaskRepository->findActive();

        return $this->render('task_execution/create.html.twig', [
            'routineTasks' => $routineTasks,
        ]);
    }

    #[Route('/create-from-routine/{routineTaskId}', name: 'app_task_execution_create_from_routine', methods: ['GET', 'POST'])]
    public function createFromRoutine(string $routineTaskId, Request $request): Response
    {
        $routineTask = $this->routineTaskRepository->findById(Uuid::fromString($routineTaskId));

        if (!$routineTask) {
            throw $this->createNotFoundException('Routine task not found');
        }

        if ($request->isMethod('POST')) {
            $command = new CreateTaskExecutionCommand(
                Uuid::generate()->value(),
                $routineTaskId,
                null,
                null,
                null,
                $request->request->get('scheduled_for') ?: (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            );

            ($this->createTaskExecutionHandler)($command);

            $this->addFlash('success', 'Task execution created from routine task!');
            return $this->redirectToRoute('app_task_execution_list');
        }

        return $this->render('task_execution/create_from_routine.html.twig', [
            'routineTask' => $routineTask,
        ]);
    }

    #[Route('/{id}', name: 'app_task_execution_view', methods: ['GET'])]
    public function view(string $id): Response
    {
        $execution = $this->taskExecutionRepository->findById(Uuid::fromString($id));

        if (!$execution) {
            throw $this->createNotFoundException('Task execution not found');
        }

        $routineTask = null;
        if ($execution->routineTaskId()) {
            $routineTask = $this->routineTaskRepository->findById($execution->routineTaskId());
        }

        return $this->render('task_execution/view.html.twig', [
            'execution' => $execution,
            'routineTask' => $routineTask,
        ]);
    }

    #[Route('/{id}/complete', name: 'app_task_execution_complete', methods: ['POST'])]
    public function complete(string $id): Response
    {
        // In real app, get user ID from security context
        $userId = Uuid::generate()->value();
        
        $command = new CompleteTaskExecutionCommand($id, $userId);
        ($this->completeTaskExecutionHandler)($command);

        $this->addFlash('success', 'Task execution completed!');
        return $this->redirectToRoute('app_task_execution_list');
    }

    #[Route('/{id}/approve', name: 'app_task_execution_approve', methods: ['POST'])]
    public function approve(string $id): Response
    {
        // In real app, get admin ID from security context
        $adminId = Uuid::generate()->value();
        
        $command = new ApproveTaskExecutionCommand($id, $adminId);
        ($this->approveTaskExecutionHandler)($command);

        $this->addFlash('success', 'Task execution approved!');
        return $this->redirectToRoute('app_task_execution_list');
    }

    #[Route('/{id}/reject', name: 'app_task_execution_reject', methods: ['POST'])]
    public function reject(string $id): Response
    {
        $execution = $this->taskExecutionRepository->findById(Uuid::fromString($id));

        if (!$execution) {
            throw $this->createNotFoundException('Task execution not found');
        }

        $execution->reject();
        $this->taskExecutionRepository->save($execution);

        $this->addFlash('success', 'Task execution rejected!');
        return $this->redirectToRoute('app_task_execution_list');
    }
}
