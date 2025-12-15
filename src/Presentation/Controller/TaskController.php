<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\CreateTaskCommand;
use App\TaskManagement\Application\Command\CreateTaskTemplateCommand;
use App\TaskManagement\Application\Command\CompleteTaskCommand;
use App\TaskManagement\Application\Command\ApproveTaskCommand;
use App\TaskManagement\Application\Handler\CreateTaskHandler;
use App\TaskManagement\Application\Handler\CreateTaskTemplateHandler;
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
        private CreateTaskTemplateHandler $createTaskTemplateHandler,
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

    #[Route('/templates', name: 'app_task_template_list', methods: ['GET'])]
    public function listTemplates(): Response
    {
        $templates = $this->taskRepository->findTemplates();

        return $this->render('task/template_list.html.twig', [
            'templates' => $templates,
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

    #[Route('/templates/create', name: 'app_task_template_create', methods: ['GET', 'POST'])]
    public function createTemplate(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $scheduleConfig = $this->buildScheduleConfig(
                $request->request->get('schedule_type'),
                $request->request->get('day_of_week'),
                $request->request->get('day_of_month'),
                $request->request->get('times_per_week')
            );

            $command = new CreateTaskTemplateCommand(
                Uuid::generate()->value(),
                $request->request->get('name'),
                $request->request->get('description'),
                (int) $request->request->get('points'),
                $request->request->get('frequency'),
                $scheduleConfig
            );

            ($this->createTaskTemplateHandler)($command);

            $this->addFlash('success', 'Task template created successfully!');
            return $this->redirectToRoute('app_task_template_list');
        }

        return $this->render('task/template_create.html.twig');
    }

    #[Route('/templates/{id}', name: 'app_task_template_view', methods: ['GET'])]
    public function viewTemplate(string $id): Response
    {
        $template = $this->taskRepository->findById(Uuid::fromString($id));

        if (!$template || !$template->isTemplate()) {
            throw $this->createNotFoundException('Task template not found');
        }

        return $this->render('task/template_view.html.twig', [
            'template' => $template,
        ]);
    }

    #[Route('/templates/{id}/activate', name: 'app_task_template_activate', methods: ['POST'])]
    public function activateTemplate(string $id): Response
    {
        $template = $this->taskRepository->findById(Uuid::fromString($id));

        if (!$template || !$template->isTemplate()) {
            throw $this->createNotFoundException('Task template not found');
        }

        $template->activate();
        $this->taskRepository->save($template);

        $this->addFlash('success', 'Task template activated!');
        return $this->redirectToRoute('app_task_template_list');
    }

    #[Route('/templates/{id}/deactivate', name: 'app_task_template_deactivate', methods: ['POST'])]
    public function deactivateTemplate(string $id): Response
    {
        $template = $this->taskRepository->findById(Uuid::fromString($id));

        if (!$template || !$template->isTemplate()) {
            throw $this->createNotFoundException('Task template not found');
        }

        $template->deactivate();
        $this->taskRepository->save($template);

        $this->addFlash('success', 'Task template deactivated!');
        return $this->redirectToRoute('app_task_template_list');
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

    private function buildScheduleConfig(
        ?string $type,
        ?string $dayOfWeek,
        ?string $dayOfMonth,
        ?string $timesPerWeek
    ): array {
        $config = ['type' => $type ?? 'daily'];

        if ($dayOfWeek) {
            $config['day_of_week'] = (int) $dayOfWeek;
        }
        if ($dayOfMonth) {
            $config['day_of_month'] = (int) $dayOfMonth;
        }
        if ($timesPerWeek) {
            $config['times_per_week'] = (int) $timesPerWeek;
        }

        return $config;
    }
}
