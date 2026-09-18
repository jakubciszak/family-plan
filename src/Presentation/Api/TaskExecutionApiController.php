<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\PointsManagement\Domain\Service\PointsLedger;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\PointsManagement\Domain\ValueObject\EntrySource;
use App\Party\Application\Service\PartyResponsibilities;
use App\Party\Domain\ValueObject\ResponsibilityType;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\Period\ClosedWeeksInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\Service\TaskTypePool;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\Exception\UnauthorizedTaskActionException;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use App\TaskManagement\Domain\Service\BonusSettlementInterface;
use App\TaskManagement\Domain\Strategy\ExecutionPointsAwardStrategyInterface;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly TeamMembershipRepositoryInterface $memberships,
        private readonly \App\ActionPlanning\Application\Service\ActionPlanAccess $actionPlans,
        private readonly ClosedWeeksInterface $closedWeeks,
        private readonly PointsLedger $ledger,
        private readonly EntityManagerInterface $entityManager
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
        $execution->attachActionPlan($this->actionPlans->forTaskType($template->actionPlanId(), $this->teamOf($template)));

        $this->executionRepository->save($execution);

        $this->responsibilities->sign(
            $this->callerId(),
            ResponsibilityType::takeTask(),
            $execution->id(),
            $teamId
        );

        return $this->json($this->serialize($execution), Response::HTTP_CREATED);
    }

    #[Route('/task-templates/{id}/assign', name: 'assign', methods: ['POST'])]
    #[OA\Post(path: '/api/task-templates/{id}/assign', summary: 'Hand a task type to a member as their own task (Admin only)', tags: ['My tasks'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['userId'],
            properties: [
                new OA\Property(property: 'userId', type: 'string', format: 'uuid', description: 'Member the task is for'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Task given to the member, waiting for them to do it')]
    #[OA\Response(response: 403, description: 'Caller does not administer this member')]
    #[OA\Response(response: 409, description: 'No runs left or the task type is retired')]
    public function assign(string $id, Request $request): JsonResponse
    {
        $template = $this->available($id);
        $member = $this->memberFrom($request, $this->teamOf($template));

        $execution = $this->handOver($template, $member, $this->clock->now());

        return $this->json($this->serialize($execution), Response::HTTP_CREATED);
    }

    #[Route('/task-templates/{id}/book', name: 'book', methods: ['POST'])]
    #[OA\Post(path: '/api/task-templates/{id}/book', summary: 'Write down a task a member already did, on the day they did it (Admin only)', tags: ['My tasks'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['userId'],
            properties: [
                new OA\Property(property: 'userId', type: 'string', format: 'uuid', description: 'Member who did it'),
                new OA\Property(
                    property: 'doneOn',
                    type: 'string',
                    format: 'date',
                    nullable: true,
                    description: 'Day it was done, at most seven days back; today when left out',
                    example: '2026-09-12'
                ),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Task written down as done and its points awarded')]
    #[OA\Response(response: 400, description: 'The day is malformed, outside the backlog window or inside a settled week')]
    #[OA\Response(response: 403, description: 'Caller does not administer this member')]
    public function book(string $id, Request $request): JsonResponse
    {
        $template = $this->available($id);
        $teamId = $this->teamOf($template);
        $member = $this->memberFrom($request, $teamId);
        $doneOn = $this->doneOn($request);

        if ($doneOn !== null && $this->closedWeeks->isClosedFor($member, $doneOn)) {
            throw new \DomainException('That week has already been settled, so nothing more can be booked into it');
        }

        $execution = $this->handOver($template, $member, $doneOn ?? $this->clock->now());

        $execution->complete($member, $this->clock, $doneOn);
        $execution->approve($this->callerId(), $this->clock);
        $this->executionRepository->save($execution);

        $this->responsibilities->sign(
            $this->callerId(),
            ResponsibilityType::approveTask(),
            $execution->id(),
            $teamId
        );

        $this->pointsAward->awardPoints($execution, $member);
        $this->bonusPayout->settleFor($member);

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
    #[OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'doneOn',
                    type: 'string',
                    format: 'date',
                    nullable: true,
                    description: 'Day the task was really done, at most seven days back',
                    example: '2026-09-12'
                ),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Task marked as done, awaiting approval')]
    #[OA\Response(response: 400, description: 'The day is malformed or outside the backlog window')]
    #[OA\Response(response: 403, description: 'Only the assignee marks their task as done')]
    public function complete(string $id, Request $request): JsonResponse
    {
        $execution = $this->execution($id);
        $teamId = $this->teamOf($this->templateOf($execution));

        if (!$this->isAssignedToCaller($execution)) {
            throw new UnauthorizedTaskActionException('Only the person who took the task can mark it as done');
        }

        $this->assertCarries(ResponsibilityType::completeTask(), $teamId);

        if (in_array($execution->status()->value, ['completed', 'approved'], true)) {
            return $this->json($this->serialize($execution));
        }

        $doneOn = $this->doneOn($request);

        if ($doneOn !== null && $this->closedWeeks->isClosedFor($this->callerId(), $doneOn)) {
            throw new \DomainException('That week has already been settled, so nothing more can be booked into it');
        }

        $execution->complete($this->callerId(), $this->clock, $doneOn);
        $this->executionRepository->save($execution);

        $this->responsibilities->sign(
            $this->callerId(),
            ResponsibilityType::completeTask(),
            $execution->id(),
            $teamId
        );

        return $this->json($this->serialize($execution));
    }

    private function doneOn(Request $request): ?DateTimeImmutable
    {
        $payload = json_decode($request->getContent() ?: '{}', true);
        $day = is_array($payload) ? ($payload['doneOn'] ?? null) : null;

        if ($day === null || $day === '') {
            return null;
        }

        $parsed = is_string($day) ? DateTimeImmutable::createFromFormat('!Y-m-d', $day) : false;

        if ($parsed === false) {
            throw new \DomainException('The day must be given as YYYY-MM-DD');
        }

        return $parsed;
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

    #[Route('/task-executions/{id}', name: 'move', methods: ['PUT'])]
    #[OA\Put(path: '/api/task-executions/{id}', summary: 'Move an approved execution within its open week (Admin only)', tags: ['My tasks'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(required: ['doneOn'], properties: [
        new OA\Property(property: 'doneOn', type: 'string', format: 'date'),
    ]))]
    #[OA\Response(response: 200, description: 'Completion date changed')]
    #[OA\Response(response: 400, description: 'Invalid date, state or settled week')]
    #[OA\Response(response: 403, description: 'Only the owning team admin can correct an execution')]
    public function move(string $id, Request $request): JsonResponse
    {
        $execution = $this->editableExecution($id);
        $value = $request->toArray()['doneOn'] ?? null;
        $day = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        if ($day === false || $day->format('Y-m-d') !== $value) {
            throw new \DomainException('The day must be given as YYYY-MM-DD');
        }

        $execution->moveTo($day, $this->clock);
        $this->executionRepository->save($execution);

        return $this->json($this->serialize($execution));
    }

    #[Route('/task-executions/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/task-executions/{id}', summary: 'Remove an approved execution and reverse its task points (Admin only)', tags: ['My tasks'])]
    #[OA\Response(response: 204, description: 'Execution removed and task points reversed')]
    #[OA\Response(response: 400, description: 'Execution is not approved or its week is settled')]
    #[OA\Response(response: 403, description: 'Only the owning team admin can correct an execution')]
    public function deleteApproved(string $id): JsonResponse
    {
        $execution = $this->editableExecution($id);

        $this->entityManager->wrapInTransaction(function () use ($execution): void {
            $points = $execution->points()?->value() ?? 0;

            if ($points !== 0) {
                $this->ledger->post(
                    $execution->assignedUserId(),
                    AccountKind::TASKS,
                    -$points,
                    EntrySource::ADJUSTMENT,
                    sprintf('Execution removed: %s', $execution->name()?->value()),
                    $execution->id(),
                    'execution-removed',
                    $execution->earnedOn()
                );
            }

            $this->executionRepository->delete($execution);
        });

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function editableExecution(string $id): TaskExecution
    {
        $execution = $this->execution($id);
        $teamId = $this->teamOf($this->templateOf($execution));

        if (!$this->memberships->isAdmin($this->callerId(), $teamId)) {
            throw new UnauthorizedTaskActionException('Only an admin of the owning team can correct an execution');
        }

        $execution->assertApproved();
        $member = $execution->assignedUserId();

        if ($member === null || $this->closedWeeks->isClosedFor($member, $execution->earnedOn())) {
            throw new \DomainException('An execution in a settled week cannot be corrected');
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
    private function available(string $templateId): TaskTemplate
    {
        $template = $this->taskTemplateRepository->findById(Uuid::fromString($templateId));

        if ($template === null) {
            throw $this->createNotFoundException('Task type not found');
        }

        if (!$template->isActive()) {
            throw new \DomainException('This task type is no longer available');
        }

        if (!$this->pool->hasRoomForAnother($template)) {
            throw new \DomainException('This task type has no runs left');
        }

        return $template;
    }

    private function memberFrom(Request $request, Uuid $teamId): Uuid
    {
        $payload = json_decode($request->getContent() ?: '{}', true);
        $wanted = is_array($payload) ? ($payload['userId'] ?? null) : null;

        if (!is_string($wanted) || !Uuid::isValid($wanted)) {
            throw new \DomainException('A member is named by their id');
        }

        $member = Uuid::fromString($wanted);

        if (!$this->memberships->isAdmin($this->callerId(), $teamId)) {
            throw new UnauthorizedTaskActionException('Only an admin of the team hands out its tasks');
        }

        if (!$this->memberships->isMember($member, $teamId)) {
            throw new UnauthorizedTaskActionException('That member does not belong to this team');
        }

        return $member;
    }

    private function handOver(TaskTemplate $template, Uuid $member, DateTimeImmutable $scheduledFor): TaskExecution
    {
        $execution = TaskExecution::takeFromTemplate(
            Uuid::generate(),
            $template->id(),
            $template->name(),
            $template->description(),
            $template->points(),
            $member,
            $scheduledFor
        );
        $execution->attachActionPlan($this->actionPlans->forTaskType($template->actionPlanId(), $this->teamOf($template)));

        $this->executionRepository->save($execution);

        $this->responsibilities->sign(
            $member,
            ResponsibilityType::takeTask(),
            $execution->id(),
            $this->teamOf($template)
        );

        return $execution;
    }

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
            'actionPlan' => $execution->actionPlan(),
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
