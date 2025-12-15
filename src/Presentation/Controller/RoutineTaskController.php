<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Command\CreateRoutineTaskCommand;
use App\TaskManagement\Application\Handler\CreateRoutineTaskHandler;
use App\TaskManagement\Domain\Repository\RoutineTaskRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/routine-tasks')]
class RoutineTaskController extends AbstractController
{
    public function __construct(
        private RoutineTaskRepositoryInterface $routineTaskRepository,
        private CreateRoutineTaskHandler $createRoutineTaskHandler
    ) {
    }

    #[Route('/', name: 'app_routine_task_list', methods: ['GET'])]
    public function list(): Response
    {
        $routineTasks = $this->routineTaskRepository->findAll();

        return $this->render('routine_task/list.html.twig', [
            'routineTasks' => $routineTasks,
        ]);
    }

    #[Route('/active', name: 'app_routine_task_active', methods: ['GET'])]
    public function active(): Response
    {
        $routineTasks = $this->routineTaskRepository->findActive();

        return $this->render('routine_task/list.html.twig', [
            'routineTasks' => $routineTasks,
        ]);
    }

    #[Route('/create', name: 'app_routine_task_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $frequency = $request->request->get('frequency');
            $scheduleConfig = $this->buildScheduleConfig(
                $request->request->get('schedule_type'),
                $request->request->get('day_of_week'),
                $request->request->get('day_of_month'),
                $request->request->get('times_per_week')
            );

            $command = new CreateRoutineTaskCommand(
                Uuid::generate()->value(),
                $request->request->get('name'),
                $request->request->get('description'),
                (int) $request->request->get('points'),
                $frequency,
                $scheduleConfig
            );

            ($this->createRoutineTaskHandler)($command);

            $this->addFlash('success', 'Routine task created successfully!');
            return $this->redirectToRoute('app_routine_task_list');
        }

        return $this->render('routine_task/create.html.twig');
    }

    #[Route('/{id}', name: 'app_routine_task_view', methods: ['GET'])]
    public function view(string $id): Response
    {
        $routineTask = $this->routineTaskRepository->findById(Uuid::fromString($id));

        if (!$routineTask) {
            throw $this->createNotFoundException('Routine task not found');
        }

        return $this->render('routine_task/view.html.twig', [
            'routineTask' => $routineTask,
        ]);
    }

    #[Route('/{id}/activate', name: 'app_routine_task_activate', methods: ['POST'])]
    public function activate(string $id): Response
    {
        $routineTask = $this->routineTaskRepository->findById(Uuid::fromString($id));

        if (!$routineTask) {
            throw $this->createNotFoundException('Routine task not found');
        }

        $routineTask->activate();
        $this->routineTaskRepository->save($routineTask);

        $this->addFlash('success', 'Routine task activated successfully!');
        return $this->redirectToRoute('app_routine_task_list');
    }

    #[Route('/{id}/deactivate', name: 'app_routine_task_deactivate', methods: ['POST'])]
    public function deactivate(string $id): Response
    {
        $routineTask = $this->routineTaskRepository->findById(Uuid::fromString($id));

        if (!$routineTask) {
            throw $this->createNotFoundException('Routine task not found');
        }

        $routineTask->deactivate();
        $this->routineTaskRepository->save($routineTask);

        $this->addFlash('success', 'Routine task deactivated successfully!');
        return $this->redirectToRoute('app_routine_task_list');
    }

    private function buildScheduleConfig(
        string $scheduleType,
        ?string $dayOfWeek,
        ?string $dayOfMonth,
        ?string $timesPerWeek
    ): array {
        $config = ['type' => $scheduleType];

        if ($scheduleType === 'weekly' && $dayOfWeek !== null) {
            $config['day_of_week'] = (int) $dayOfWeek;
        } elseif ($scheduleType === 'monthly' && $dayOfMonth !== null) {
            $config['day_of_month'] = (int) $dayOfMonth;
        } elseif ($scheduleType === 'times_per_week' && $timesPerWeek !== null) {
            $config['times_per_week'] = (int) $timesPerWeek;
        }

        return $config;
    }
}
