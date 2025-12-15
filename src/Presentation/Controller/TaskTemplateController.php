<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\CreateTaskTemplateCommand;
use App\TaskManagement\Application\Handler\CreateTaskTemplateHandler;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/task-templates')]
class TaskTemplateController extends AbstractController
{
    public function __construct(
        private TaskTemplateRepositoryInterface $taskTemplateRepository,
        private CreateTaskTemplateHandler $createTaskTemplateHandler
    ) {
    }

    #[Route('/', name: 'app_task_template_list', methods: ['GET'])]
    public function list(): Response
    {
        $taskTemplates = $this->taskTemplateRepository->findAll();

        return $this->render('task_template/list.html.twig', [
            'taskTemplates' => $taskTemplates,
        ]);
    }

    #[Route('/create', name: 'app_task_template_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $scheduleType = $request->request->get('schedule_type');
            $scheduleConfig = ['type' => $scheduleType];
            
            if ($scheduleType === 'weekly') {
                $scheduleConfig['day_of_week'] = (int) $request->request->get('day_of_week');
            } elseif ($scheduleType === 'monthly') {
                $scheduleConfig['day_of_month'] = (int) $request->request->get('day_of_month');
            } elseif ($scheduleType === 'times_per_week') {
                $scheduleConfig['times_per_week'] = (int) $request->request->get('times_per_week');
            }

            $command = new CreateTaskTemplateCommand(
                Uuid::generate()->value(),
                $request->request->get('name'),
                $request->request->get('description'),
                (int) $request->request->get('points'),
                $request->request->get('frequency'),
                $scheduleConfig
            );

            ($this->createTaskTemplateHandler)($command);

            return $this->redirectToRoute('app_task_template_list');
        }

        return $this->render('task_template/create.html.twig');
    }

    #[Route('/{id}', name: 'app_task_template_view', methods: ['GET'])]
    public function view(string $id): Response
    {
        $taskTemplate = $this->taskTemplateRepository->findById(Uuid::fromString($id));

        if (!$taskTemplate) {
            throw $this->createNotFoundException('Task template not found');
        }

        return $this->render('task_template/view.html.twig', [
            'taskTemplate' => $taskTemplate,
        ]);
    }

    #[Route('/{id}/activate', name: 'app_task_template_activate', methods: ['POST'])]
    public function activate(string $id): Response
    {
        $taskTemplate = $this->taskTemplateRepository->findById(Uuid::fromString($id));

        if (!$taskTemplate) {
            throw $this->createNotFoundException('Task template not found');
        }

        $taskTemplate->activate();
        $this->taskTemplateRepository->save($taskTemplate);

        return $this->redirectToRoute('app_task_template_view', ['id' => $id]);
    }

    #[Route('/{id}/deactivate', name: 'app_task_template_deactivate', methods: ['POST'])]
    public function deactivate(string $id): Response
    {
        $taskTemplate = $this->taskTemplateRepository->findById(Uuid::fromString($id));

        if (!$taskTemplate) {
            throw $this->createNotFoundException('Task template not found');
        }

        $taskTemplate->deactivate();
        $this->taskTemplateRepository->save($taskTemplate);

        return $this->redirectToRoute('app_task_template_view', ['id' => $id]);
    }
}
