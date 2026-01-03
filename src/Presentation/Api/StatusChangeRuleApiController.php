<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\StatusChangeRule\Command\ActivateStatusChangeRuleCommand;
use App\TaskManagement\Application\StatusChangeRule\Command\CreateStatusChangeRuleCommand;
use App\TaskManagement\Application\StatusChangeRule\Command\DeactivateStatusChangeRuleCommand;
use App\TaskManagement\Application\StatusChangeRule\Command\UpdateStatusChangeRuleCommand;
use App\TaskManagement\Application\StatusChangeRule\Query\FindStatusChangeRuleByIdQuery;
use App\TaskManagement\Application\StatusChangeRule\Query\GetAllStatusChangeRulesQuery;
use App\TaskManagement\Domain\Entity\StatusChangeRule;
use App\Presentation\Api\Dto\StatusChangeRule\CreateStatusChangeRuleRequest;
use App\Presentation\Api\Dto\StatusChangeRule\UpdateStatusChangeRuleRequest;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/status-change-rules', name: 'api_status_change_rule_')]
#[OA\Tag(name: 'Status Change Rules')]
#[IsGranted('ROLE_ADMIN')]
class StatusChangeRuleApiController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/status-change-rules',
        summary: 'List all status change rules (Admin only)',
        tags: ['Status Change Rules']
    )]
    #[OA\Parameter(
        name: 'active',
        in: 'query',
        description: 'Filter by active status',
        required: false,
        schema: new OA\Schema(type: 'boolean')
    )]
    #[OA\Parameter(
        name: 'taskTemplateId',
        in: 'query',
        description: 'Filter by task template ID',
        required: false,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'List of status change rules',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'rules',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'taskTemplateId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'conditionType', type: 'string'),
                            new OA\Property(property: 'isActive', type: 'boolean'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                        ]
                    )
                )
            ]
        )
    )]
    public function list(Request $request): JsonResponse
    {
        $activeOnly = $request->query->getBoolean('active', false);
        $taskTemplateId = $request->query->get('taskTemplateId');
        
        $rules = $this->queryBus->dispatch(new GetAllStatusChangeRulesQuery($activeOnly, $taskTemplateId))
            ->last(HandledStamp::class)->getResult();

        return $this->json([
            'rules' => array_map(fn(StatusChangeRule $rule) => $this->serializeRule($rule), $rules),
        ]);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/status-change-rules/{id}',
        summary: 'Get a status change rule by ID (Admin only)',
        tags: ['Status Change Rules']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Status change rule details'
    )]
    #[OA\Response(
        response: 404,
        description: 'Rule not found'
    )]
    public function get(string $id): JsonResponse
    {
        $rule = $this->queryBus->dispatch(new FindStatusChangeRuleByIdQuery($id))
            ->last(HandledStamp::class)->getResult();

        if ($rule === null) {
            return $this->json(['error' => 'Rule not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeRule($rule));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/status-change-rules',
        summary: 'Create a new status change rule (Admin only)',
        tags: ['Status Change Rules']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['taskTemplateId', 'name', 'description', 'conditionType', 'conditionConfig'],
            properties: [
                new OA\Property(property: 'taskTemplateId', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string', example: 'Cooldown After Completion'),
                new OA\Property(property: 'description', type: 'string', example: 'Task cannot be assigned within 2 days of last execution'),
                new OA\Property(property: 'conditionType', type: 'string', enum: ['other_task_completed_today', 'last_execution_cooldown']),
                new OA\Property(
                    property: 'conditionConfig',
                    type: 'object',
                    example: ['cooldownDays' => 2]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Rule created successfully'
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation error'
    )]
    public function create(
        #[MapRequestPayload] CreateStatusChangeRuleRequest $request
    ): JsonResponse {
        $command = new CreateStatusChangeRuleCommand(
            Uuid::generate()->value(),
            $request->taskTemplateId,
            $request->name,
            $request->description,
            $request->conditionType,
            $request->conditionConfig
        );

        try {
            $this->commandBus->dispatch($command);
            return $this->json(['message' => 'Rule created successfully'], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/status-change-rules/{id}',
        summary: 'Update a status change rule (Admin only)',
        tags: ['Status Change Rules']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'description'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Rule updated successfully'
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation error'
    )]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateStatusChangeRuleRequest $request
    ): JsonResponse {
        $command = new UpdateStatusChangeRuleCommand(
            $id,
            $request->name,
            $request->description
        );

        try {
            $this->commandBus->dispatch($command);
            return $this->json(['message' => 'Rule updated successfully']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/activate', name: 'activate', methods: ['POST'])]
    #[OA\Post(
        path: '/api/status-change-rules/{id}/activate',
        summary: 'Activate a status change rule (Admin only)',
        tags: ['Status Change Rules']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Rule activated successfully'
    )]
    public function activate(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new ActivateStatusChangeRuleCommand($id));

        return $this->json(['message' => 'Rule activated successfully']);
    }

    #[Route('/{id}/deactivate', name: 'deactivate', methods: ['POST'])]
    #[OA\Post(
        path: '/api/status-change-rules/{id}/deactivate',
        summary: 'Deactivate a status change rule (Admin only)',
        tags: ['Status Change Rules']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Rule deactivated successfully'
    )]
    public function deactivate(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeactivateStatusChangeRuleCommand($id));

        return $this->json(['message' => 'Rule deactivated successfully']);
    }

    private function serializeRule(StatusChangeRule $rule): array
    {
        return [
            'id' => $rule->id()->value(),
            'taskTemplateId' => $rule->taskTemplateId()->value(),
            'name' => $rule->name(),
            'description' => $rule->description(),
            'conditionType' => $rule->conditionType()->value,
            'config' => $rule->config()->toArray(),
            'isActive' => $rule->isActive(),
            'createdAt' => $rule->createdAt()->format('c'),
            'updatedAt' => $rule->updatedAt()?->format('c'),
        ];
    }
}
