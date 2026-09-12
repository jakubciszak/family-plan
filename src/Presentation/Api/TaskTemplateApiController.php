<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Party\Application\Service\PartyResponsibilities;
use App\Party\Domain\ValueObject\ResponsibilityType;
use App\Presentation\Api\Dto\TaskType\CreateTaskTypeRequest;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Application\Service\TaskTypePool;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\ExecutionLimit;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TeamManagement\Domain\Exception\UnauthorizedTeamActionException;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/task-templates', name: 'api_task_template_')]
#[OA\Tag(name: 'Task types')]
#[IsGranted('ROLE_USER')]
class TaskTemplateApiController extends AbstractController
{
    public function __construct(
        private readonly TaskTemplateRepositoryInterface $taskTemplateRepository,
        private readonly TaskTypePool $pool,
        private readonly PartyResponsibilities $responsibilities,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(path: '/api/task-templates', summary: 'List task types of the caller teams', tags: ['Task types'])]
    #[OA\Response(response: 200, description: 'Task types with how many runs are left')]
    public function list(): JsonResponse
    {
        $teamIds = $this->callerTeamIds();

        $templates = array_filter(
            $this->taskTemplateRepository->findAll(),
            fn (TaskTemplate $template) => $template->teamId() !== null
                && in_array($template->teamId()->value(), $teamIds, true)
        );

        return $this->json([
            'templates' => array_values(array_map(
                fn (TaskTemplate $template) => $this->serialize($template),
                $templates
            )),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(path: '/api/task-templates', summary: 'Define a task type', tags: ['Task types'])]
    #[OA\Response(response: 201, description: 'Task type created')]
    #[OA\Response(response: 400, description: 'Invalid execution limit')]
    #[OA\Response(response: 403, description: 'Only team admins define task types')]
    public function create(#[MapRequestPayload] CreateTaskTypeRequest $request): JsonResponse
    {
        $teamId = Uuid::fromString($request->teamId);
        $this->assertTeamAdmin($teamId);

        try {
            $limit = ExecutionLimit::fromArray($request->executionLimit);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $frequency = Frequency::fromString($request->frequency);

        $template = TaskTemplate::create(
            Uuid::generate(),
            TaskName::fromString($request->name),
            $request->description,
            Points::fromInt($request->points),
            $frequency,
            $this->scheduleFor($frequency),
            null,
            $limit,
            $teamId
        );

        $this->taskTemplateRepository->save($template);

        return $this->json($this->serialize($template), Response::HTTP_CREATED);
    }

    #[Route('/{id}/activate', name: 'activate', methods: ['POST'])]
    #[OA\Post(path: '/api/task-templates/{id}/activate', summary: 'Make a task type available again', tags: ['Task types'])]
    #[OA\Response(response: 200, description: 'Task type activated')]
    public function activate(string $id): JsonResponse
    {
        return $this->changeAvailability($id, true);
    }

    #[Route('/{id}/deactivate', name: 'deactivate', methods: ['POST'])]
    #[OA\Post(path: '/api/task-templates/{id}/deactivate', summary: 'Take a task type off the list', tags: ['Task types'])]
    #[OA\Response(response: 200, description: 'Task type deactivated')]
    public function deactivate(string $id): JsonResponse
    {
        return $this->changeAvailability($id, false);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/task-templates/{id}', summary: 'Remove a task type', tags: ['Task types'])]
    #[OA\Response(response: 204, description: 'Task type removed')]
    public function delete(string $id): JsonResponse
    {
        $template = $this->ownedTemplate($id);
        $this->taskTemplateRepository->delete($template);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function changeAvailability(string $id, bool $active): JsonResponse
    {
        $template = $this->ownedTemplate($id);

        $active ? $template->activate() : $template->deactivate();
        $this->taskTemplateRepository->save($template);

        return $this->json($this->serialize($template));
    }

    private function ownedTemplate(string $id): TaskTemplate
    {
        $template = $this->taskTemplateRepository->findById(Uuid::fromString($id));

        if ($template === null) {
            throw $this->createNotFoundException('Task type not found');
        }

        if ($template->teamId() === null) {
            throw new UnauthorizedTeamActionException('This task type does not belong to a team');
        }

        $this->assertTeamAdmin($template->teamId());

        return $template;
    }

    private function assertTeamAdmin(Uuid $teamId): void
    {
        $mayDefine = $this->responsibilities->partyMay(
            $this->callerId(),
            ResponsibilityType::defineTaskType(),
            $teamId
        );

        if (!$mayDefine) {
            throw new UnauthorizedTeamActionException('Only team admins manage task types');
        }
    }

    private function callerId(): Uuid
    {
        return $this->userRepository
            ->findByEmail(Email::fromString($this->getUser()->getUserIdentifier()))
            ->id();
    }

    /**
     * @return string[]
     */
    private function callerTeamIds(): array
    {
        return array_values(array_unique(array_merge(
            $this->responsibilities->organizationsWhereMay($this->callerId(), ResponsibilityType::takeTask()),
            $this->responsibilities->organizationsWhereMay($this->callerId(), ResponsibilityType::defineTaskType())
        )));
    }

    private function scheduleFor(Frequency $frequency): ScheduleConfig
    {
        return match ($frequency->value) {
            'daily' => ScheduleConfig::daily(),
            'weekly' => ScheduleConfig::weeklyOnDay(1),
            'monthly' => ScheduleConfig::monthlyOnDay(1),
            default => ScheduleConfig::once(),
        };
    }

    private function serialize(TaskTemplate $template): array
    {
        return [
            'id' => $template->id()->value(),
            'teamId' => $template->teamId()?->value(),
            'name' => $template->name()->value(),
            'description' => $template->description(),
            'points' => $template->points()->value(),
            'frequency' => $template->frequency()->value,
            'executionLimit' => $template->executionLimit()->toArray(),
            'remaining' => $this->pool->remaining($template),
            'isActive' => $template->isActive(),
        ];
    }
}
