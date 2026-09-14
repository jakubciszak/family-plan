<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Party\Application\Service\PartyResponsibilities;
use App\Shared\Domain\Clock\ClockInterface;
use App\Party\Domain\ValueObject\ResponsibilityType;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Service\TaskTypePool;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\Exception\UnauthorizedTaskActionException;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use App\TaskManagement\Domain\Service\BonusSettlementInterface;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TaskManagement\Domain\Strategy\ExecutionPointsAwardStrategyInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use DateTimeImmutable;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_task_execution_')]
#[OA\Tag(name: 'My tasks')]
#[IsGranted('ROLE_USER')]
class TaskExecutionApiController extends AbstractController
{
    public function __construct(
        private readonly TaskExecutionRepositoryInterface $executionRepository,
        private readonly TaskTemplateRepositoryInterface $taskTemplateRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ExecutionPointsAwardStrategyInterface $pointsAward,
        private readonly TaskTypePool $pool,
        private readonly PartyResponsibilities $responsibilities,
        private readonly ClockInterface $clock,
        private readonly BonusSettlementInterface $bonusPayout,
        private readonly TeamMembershipRepositoryInterface $memberships
    ) {
    }

    #[Route('/task-templates/{id}/take', name: 'take', methods: ['POST'])]
    #[OA\Post(path: '/api/task-templates/{id}/take', summary: 'Take a task type and make it my own task', tags: ['My tasks'])]
    #[OA\Response(response: 201, description: 'Task taken')]
    #[OA\Response(response: 403, description: 'Caller is not a member of the owning team')]
    #[OA\Response(response: 409, description: 'No runs left or the task type is retired')]
    public function take(string $id): JsonResponse
    {
        $template = $this->taskTemplateRepository->findById(Uuid::fromString($id));

        if ($template === null) {
            throw $this->createNotFoundException('Task type not found');
        }

        $teamId = $this->teamOf($template);
        $this->assertCarries(ResponsibilityType::takeTask(), $teamId);

        if (!$template->isActive()) {
            return $this->json(['error' => 'This task type is no longer available'], Response::HTTP_CONFLICT);
        }

        if (!$this->pool->hasRoomForAnother($template)) {
            return $this->json(['error' => 'This task type has no runs left'], Response::HTTP_CONFLICT);
        }

        $execution = TaskExecution::takeFromTemplate(
            Uuid::generate(),
            $template->id(),
            $template->name(),
            $template->description(),
            $template->points(),
            $this->callerId(),
            new DateTimeImmutable()
        );

        $this->executionRepository->save($execution);

        $this->responsibilities->sign(
            $this->callerId(),
            ResponsibilityType::takeTask(),
            $execution->id(),
            $teamId
        );

        return $this->json($this->serialize($execution), Response::HTTP_CREATED);
    }

    #[Route('/task-executions/mine', name: 'mine', methods: ['GET'])]
    #[OA\Get(path: '/api/task-executions/mine', summary: 'List the tasks I have taken', tags: ['My tasks'])]
    #[OA\Response(response: 200, description: 'My tasks')]
    public function mine(): JsonResponse
    {
        $executions = $this->executionRepository->findByAssignedUser($this->callerId());

        return $this->json([
            'executions' => array_values(array_map(
                fn (TaskExecution $execution) => $this->serialize($execution),
                $executions
            )),
        ]);
    }

    #[Route('/task-executions/of/{userId}', name: 'of_member', methods: ['GET'])]
    #[OA\Get(path: '/api/task-executions/of/{userId}', summary: 'List the tasks a member of my team has taken', tags: ['My tasks'])]
    #[OA\Response(response: 200, description: 'Tasks of that member')]
    #[OA\Response(response: 403, description: 'Caller does not administer a team of that member')]
    public function ofMember(string $userId): JsonResponse
    {
        $member = Uuid::fromString($userId);
        $this->assertAdministersATeamOf($member);

        return $this->json([
            'executions' => array_values(array_map(
                fn (TaskExecution $execution) => $this->serialize($execution),
                $this->executionRepository->findByAssignedUser($member)
            )),
        ]);
    }

    #[Route('/task-executions/awaiting-approval', name: 'awaiting_approval', methods: ['GET'])]
    #[OA\Get(path: '/api/task-executions/awaiting-approval', summary: 'List finished tasks waiting for my approval', tags: ['My tasks'])]
    #[OA\Response(response: 200, description: 'Finished tasks of the teams I administer')]
    public function awaitingApproval(): JsonResponse
    {
        $adminTeamIds = $this->adminTeamIds();

        $awaiting = array_filter(
            $this->executionRepository->findCompleted(),
            function (TaskExecution $execution) use ($adminTeamIds): bool {
                $templateId = $execution->taskTemplateId();
                $template = $templateId === null ? null : $this->taskTemplateRepository->findById($templateId);

                return $template?->teamId() !== null
                    && in_array($template->teamId()->value(), $adminTeamIds, true);
            }
        );

        return $this->json([
            'executions' => array_values(array_map(
                fn (TaskExecution $execution) => $this->serialize($execution),
                $awaiting
            )),
        ]);
    }

    #[Route('/task-executions/{id}/complete', name: 'complete', methods: ['POST'])]
    #[OA\Post(path: '/api/task-executions/{id}/complete', summary: 'Mark my task as done', tags: ['My tasks'])]
    #[OA\Response(response: 200, description: 'Task marked as done, awaiting approval')]
    #[OA\Response(response: 403, description: 'Only the assignee marks their task as done')]
    public function complete(string $id): JsonResponse
    {
        $execution = $this->execution($id);
        $teamId = $this->teamOf($this->templateOf($execution));

        if (!$this->isAssignedToCaller($execution)) {
            throw new UnauthorizedTaskActionException('Only the person who took the task can mark it as done');
        }

        $this->assertCarries(ResponsibilityType::completeTask(), $teamId);

        $execution->complete($this->callerId(), $this->clock);
        $this->executionRepository->save($execution);

        $this->responsibilities->sign(
            $this->callerId(),
            ResponsibilityType::completeTask(),
            $execution->id(),
            $teamId
        );

        return $this->json($this->serialize($execution));
    }

    #[Route('/task-executions/{id}/approve', name: 'approve', methods: ['POST'])]
    #[OA\Post(path: '/api/task-executions/{id}/approve', summary: 'Approve a finished task and award its points', tags: ['My tasks'])]
    #[OA\Response(response: 200, description: 'Task approved and points awarded')]
    #[OA\Response(response: 403, description: 'Only team admins approve tasks')]
    public function approve(string $id): JsonResponse
    {
        $execution = $this->execution($id);
        $teamId = $this->teamOf($this->templateOf($execution));
        $this->assertCarries(ResponsibilityType::approveTask(), $teamId);

        if ($this->isAssignedToCaller($execution)) {
            throw new UnauthorizedTaskActionException('Nobody approves their own task');
        }

        $execution->approve($this->callerId(), $this->clock);
        $this->executionRepository->save($execution);

        $this->responsibilities->sign(
            $this->callerId(),
            ResponsibilityType::approveTask(),
            $execution->id(),
            $teamId
        );

        $assignee = $execution->assignedUserId();
        if ($assignee !== null) {
            $this->pointsAward->awardPoints($execution, $assignee);
            $this->bonusPayout->settleFor($assignee);
        }

        return $this->json($this->serialize($execution));
    }

    #[Route('/task-executions/{id}/reject', name: 'reject', methods: ['POST'])]
    #[OA\Post(path: '/api/task-executions/{id}/reject', summary: 'Send a finished task back as not done', tags: ['My tasks'])]
    #[OA\Response(response: 200, description: 'Task rejected, no points awarded')]
    #[OA\Response(response: 400, description: 'A rejection needs a reason')]
    #[OA\Response(response: 403, description: 'Only team admins judge a finished task')]
    public function reject(string $id, Request $request): JsonResponse
    {
        $reason = trim((string) ($request->toArray()['reason'] ?? ''));

        if ($reason === '') {
            return $this->json(
                ['error' => 'A rejection needs a reason the assignee can act on'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $execution = $this->execution($id);
        $teamId = $this->teamOf($this->templateOf($execution));
        $this->assertCarries(ResponsibilityType::approveTask(), $teamId);

        if ($this->isAssignedToCaller($execution)) {
            throw new UnauthorizedTaskActionException('Nobody judges their own task');
        }

        $execution->reject($reason);
        $this->executionRepository->save($execution);

        return $this->json($this->serialize($execution));
    }

    #[Route('/task-executions/{id}/abandon', name: 'abandon', methods: ['POST'])]
    #[OA\Post(path: '/api/task-executions/{id}/abandon', summary: 'Give a taken task back to the pool', tags: ['My tasks'])]
    #[OA\Response(response: 204, description: 'Task returned to the pool')]
    #[OA\Response(response: 403, description: 'Only the assignee or a team admin gives a task back')]
    #[OA\Response(response: 409, description: 'A finished task cannot be given back')]
    public function abandon(string $id): JsonResponse
    {
        $execution = $this->execution($id);

        if (!$this->isAssignedToCaller($execution) && !$this->carriesAssignment($execution)) {
            throw new UnauthorizedTaskActionException('Only the person who took the task or a team admin can give it back');
        }

        if (!$execution->isOpen()) {
            return $this->json(['error' => 'A finished task cannot be given back'], Response::HTTP_CONFLICT);
        }

        $this->executionRepository->delete($execution);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function execution(string $id): TaskExecution
    {
        $execution = $this->executionRepository->findById(Uuid::fromString($id));

        if ($execution === null) {
            throw $this->createNotFoundException('Task not found');
        }

        return $execution;
    }

    private function templateOf(TaskExecution $execution): TaskTemplate
    {
        $templateId = $execution->taskTemplateId();
        $template = $templateId === null ? null : $this->taskTemplateRepository->findById($templateId);

        if ($template === null) {
            throw new UnauthorizedTaskActionException('This task is not tied to a task type');
        }

        return $template;
    }

    private function teamOf(TaskTemplate $template): Uuid
    {
        $teamId = $template->teamId();

        if ($teamId === null) {
            throw new UnauthorizedTaskActionException('This task type does not belong to a team');
        }

        return $teamId;
    }

    private function isAssignedToCaller(TaskExecution $execution): bool
    {
        return $execution->assignedUserId()?->value() === $this->callerId()->value();
    }

    private function carriesAssignment(TaskExecution $execution): bool
    {
        return $this->responsibilities->partyMay(
            $this->callerId(),
            ResponsibilityType::assignTask(),
            $this->teamOf($this->templateOf($execution))
        );
    }

    private function assertCarries(ResponsibilityType $type, Uuid $teamId): void
    {
        if (!$this->responsibilities->partyMay($this->callerId(), $type, $teamId)) {
            throw new UnauthorizedTaskActionException(sprintf(
                'This account carries no %s responsibility in that team',
                $type->value()
            ));
        }
    }

    /**
     * @return string[]
     */
    private function assertAdministersATeamOf(Uuid $member): void
    {
        foreach ($this->memberships->ofUser($member) as $membership) {
            if ($this->memberships->isAdmin($this->callerId(), $membership->teamId())) {
                return;
            }
        }

        throw new UnauthorizedTaskActionException('Only an admin of their team looks at another member');
    }

    private function adminTeamIds(): array
    {
        return $this->responsibilities->organizationsWhereMay(
            $this->callerId(),
            ResponsibilityType::approveTask()
        );
    }

    private function callerId(): Uuid
    {
        return $this->userRepository
            ->findByEmail(Email::fromString($this->getUser()->getUserIdentifier()))
            ->id();
    }

    private function assigneeName(TaskExecution $execution): ?string
    {
        $assignee = $execution->assignedUserId();

        return $assignee === null ? null : $this->userRepository->findById($assignee)?->name();
    }

    private function serialize(TaskExecution $execution): array
    {
        return [
            'id' => $execution->id()->value(),
            'taskTemplateId' => $execution->taskTemplateId()?->value(),
            'name' => $execution->name()?->value(),
            'description' => $execution->description(),
            'points' => $execution->points()?->value(),
            'status' => $execution->status()->value,
            'assignedUserId' => $execution->assignedUserId()?->value(),
            'assignedUserName' => $this->assigneeName($execution),
            'completedAt' => $execution->completedAt()?->format(DATE_ATOM),
            'approvedAt' => $execution->approvedAt()?->format(DATE_ATOM),
            'createdAt' => $execution->createdAt()->format(DATE_ATOM),
            'rejectionReason' => $execution->rejectionReason(),
        ];
    }
}
