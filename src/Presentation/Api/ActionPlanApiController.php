<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\ActionPlanning\Application\Command\DeleteActionPlanCommand;
use App\ActionPlanning\Application\Command\SaveActionPlanCommand;
use App\ActionPlanning\Application\Query\ActionPlansView;
use App\ActionPlanning\Domain\Exception\ActionPlanNotFound;
use App\Presentation\Api\Dto\ActionPlanning\ActionPlanRequest;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/action-plans', name: 'api_action_plan_', format: 'json')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Action plans')]
final class ActionPlanApiController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly ActionPlansView $plans,
        private readonly UserRepositoryInterface $users,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(summary: 'List my saved action plans')]
    #[OA\Response(response: 200, description: 'Plans owned by the current user')]
    public function list(): JsonResponse
    {
        return $this->json(['plans' => $this->plans->ofUser($this->caller())]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(summary: 'Save a reusable action plan')]
    #[OA\Response(response: 201, description: 'Saved plan')]
    public function create(#[MapRequestPayload] ActionPlanRequest $request): JsonResponse
    {
        return $this->save($request, null, 201);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    #[OA\Put(summary: 'Update my action plan')]
    #[OA\Response(response: 200, description: 'Updated plan')]
    #[OA\Response(response: 404, description: 'No owned plan with this ID')]
    public function update(string $id, #[MapRequestPayload] ActionPlanRequest $request): JsonResponse
    {
        return $this->save($request, $this->planId($id), 200);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(summary: 'Delete my action plan')]
    #[OA\Response(response: 204, description: 'Plan deleted')]
    #[OA\Response(response: 404, description: 'No owned plan with this ID')]
    public function delete(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeleteActionPlanCommand($this->caller(), $this->planId($id)));

        return $this->json(null, 204);
    }

    private function save(ActionPlanRequest $request, ?Uuid $id, int $status): JsonResponse
    {
        $result = $this->commandBus->dispatch(new SaveActionPlanCommand(
            $this->caller(), $id, $request->name, $request->steps, $request->estimatedMinutes, $request->reminderMinutes,
            $request->teamId === null ? null : Uuid::fromString($request->teamId),
            $request->reminderSound
        ));

        return $this->json($result->last(HandledStamp::class)->getResult(), $status);
    }

    private function planId(string $id): Uuid
    {
        try {
            return Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new ActionPlanNotFound();
        }
    }

    private function caller(): Uuid
    {
        $user = $this->users->findByEmail(Email::fromString($this->getUser()->getUserIdentifier()));

        return $user?->id() ?? throw $this->createAccessDeniedException();
    }
}
