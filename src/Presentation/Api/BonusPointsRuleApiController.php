<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\BonusRule\Command\ActivateBonusPointsRuleCommand;
use App\TaskManagement\Application\BonusRule\Command\CreateBonusPointsRuleCommand;
use App\TaskManagement\Application\BonusRule\Command\DeactivateBonusPointsRuleCommand;
use App\TaskManagement\Application\BonusRule\Command\UpdateBonusPointsRuleCommand;
use App\TaskManagement\Application\BonusRule\Query\FindBonusPointsRuleByIdQuery;
use App\TaskManagement\Application\BonusRule\Query\GetAllBonusPointsRulesQuery;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/bonus-rules', name: 'api_bonus_rule_')]
#[OA\Tag(name: 'Bonus Points Rules')]
#[IsGranted('ROLE_ADMIN')]
class BonusPointsRuleApiController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly MessageBusInterface $queryBus
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/bonus-rules',
        summary: 'List all bonus points rules (Admin only)',
        tags: ['Bonus Points Rules']
    )]
    #[OA\Parameter(
        name: 'active',
        in: 'query',
        description: 'Filter by active status',
        required: false,
        schema: new OA\Schema(type: 'boolean')
    )]
    #[OA\Response(
        response: 200,
        description: 'List of bonus points rules',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'rules',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'description', type: 'string'),
                            new OA\Property(property: 'bonusPoints', type: 'integer'),
                            new OA\Property(property: 'type', type: 'string', enum: ['consecutive_days', 'monthly_task_count']),
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
        $rules = $this->queryBus->dispatch(new GetAllBonusPointsRulesQuery($activeOnly))
            ->last(HandledStamp::class)->getResult();

        return $this->json([
            'rules' => array_map(fn(BonusPointsRule $rule) => $this->serializeRule($rule), $rules),
        ]);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/bonus-rules/{id}',
        summary: 'Get a bonus points rule by ID (Admin only)',
        tags: ['Bonus Points Rules']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid')
    )]
    #[OA\Response(
        response: 200,
        description: 'Bonus points rule details'
    )]
    #[OA\Response(
        response: 404,
        description: 'Rule not found'
    )]
    public function get(string $id): JsonResponse
    {
        $rule = $this->queryBus->dispatch(new FindBonusPointsRuleByIdQuery($id))
            ->last(HandledStamp::class)->getResult();

        if ($rule === null) {
            return $this->json(['error' => 'Rule not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeRule($rule));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/bonus-rules',
        summary: 'Create a new bonus points rule (Admin only)',
        tags: ['Bonus Points Rules']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'description', 'bonusPoints', 'ruleType', 'ruleConfig'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Dishwasher Streak'),
                new OA\Property(property: 'description', type: 'string', example: 'Earn 20 points for 5 consecutive days'),
                new OA\Property(property: 'bonusPoints', type: 'integer', example: 20),
                new OA\Property(property: 'ruleType', type: 'string', enum: ['consecutive_days', 'monthly_task_count']),
                new OA\Property(
                    property: 'ruleConfig',
                    type: 'object',
                    example: ['taskTemplateId' => '123e4567-e89b-12d3-a456-426614174000', 'requiredDays' => 5]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Rule created successfully'
    )]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $command = new CreateBonusPointsRuleCommand(
            Uuid::generate()->value(),
            $data['name'],
            $data['description'],
            $data['bonusPoints'],
            $data['ruleType'],
            $data['ruleConfig']
        );

        $this->commandBus->dispatch($command);

        return $this->json(['message' => 'Rule created successfully'], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/bonus-rules/{id}',
        summary: 'Update a bonus points rule (Admin only)',
        tags: ['Bonus Points Rules']
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
            required: ['name', 'description', 'bonusPoints'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'bonusPoints', type: 'integer')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Rule updated successfully'
    )]
    public function update(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $command = new UpdateBonusPointsRuleCommand(
            $id,
            $data['name'],
            $data['description'],
            $data['bonusPoints']
        );

        $this->commandBus->dispatch($command);

        return $this->json(['message' => 'Rule updated successfully']);
    }

    #[Route('/{id}/activate', name: 'activate', methods: ['POST'])]
    #[OA\Post(
        path: '/api/bonus-rules/{id}/activate',
        summary: 'Activate a bonus points rule (Admin only)',
        tags: ['Bonus Points Rules']
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
        $this->commandBus->dispatch(new ActivateBonusPointsRuleCommand($id));

        return $this->json(['message' => 'Rule activated successfully']);
    }

    #[Route('/{id}/deactivate', name: 'deactivate', methods: ['POST'])]
    #[OA\Post(
        path: '/api/bonus-rules/{id}/deactivate',
        summary: 'Deactivate a bonus points rule (Admin only)',
        tags: ['Bonus Points Rules']
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
        $this->commandBus->dispatch(new DeactivateBonusPointsRuleCommand($id));

        return $this->json(['message' => 'Rule deactivated successfully']);
    }

    private function serializeRule(BonusPointsRule $rule): array
    {
        return [
            'id' => $rule->id()->value(),
            'name' => $rule->name(),
            'description' => $rule->description(),
            'bonusPoints' => $rule->bonusPoints()->value(),
            'type' => $rule->type()->value,
            'config' => $rule->config()->toArray(),
            'isActive' => $rule->isActive(),
            'createdAt' => $rule->createdAt()->format('c'),
            'updatedAt' => $rule->updatedAt()?->format('c'),
        ];
    }
}
