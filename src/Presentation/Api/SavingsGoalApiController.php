<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Allowance\Application\Command\AdjustGoalCommand;
use App\Allowance\Application\Command\CloseGoalCommand;
use App\Allowance\Application\Command\PlanGoalCommand;
use App\Allowance\Application\Command\PutAsideForGoalCommand;
use App\Allowance\Application\Command\SpendGoalCommand;
use App\Allowance\Application\Command\TakeBackFromGoalCommand;
use App\Allowance\Application\Service\AllowanceAccess;
use App\Allowance\Application\Service\GoalsView;
use App\Allowance\Domain\Repository\SavingsGoalRepositoryInterface;
use App\Presentation\Api\Dto\Allowance\GoalAmountRequest;
use App\Presentation\Api\Dto\Allowance\GoalRequest;
use App\Shared\Domain\ValueObject\Uuid;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/allowance/goals', name: 'api_allowance_goal_')]
#[OA\Tag(name: 'Allowance')]
#[IsGranted('ROLE_USER')]
class SavingsGoalApiController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly GoalsView $goals,
        private readonly SavingsGoalRepositoryInterface $repository,
        private readonly AllowanceAccess $access
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(path: '/api/allowance/goals', summary: 'What someone is saving up for, and how far along they are', tags: ['Allowance'])]
    #[OA\Parameter(name: 'userId', in: 'query', required: false, description: 'Member to look at; defaults to the caller')]
    #[OA\Parameter(name: 'all', in: 'query', required: false, description: 'Include goals that were closed')]
    #[OA\Response(response: 200, description: 'Goals with what is put aside and how long it would take at this pace')]
    public function list(Request $request): JsonResponse
    {
        $userId = $this->access->inspected($this->caller(), $request->query->get('userId'));

        return $this->json($this->goals->of($userId, !$request->query->getBoolean('all', false)));
    }

    #[Route('', name: 'plan', methods: ['POST'])]
    #[OA\Post(path: '/api/allowance/goals', summary: 'Start saving for something', tags: ['Allowance'])]
    #[OA\Response(response: 201, description: 'Goal planned')]
    public function plan(#[MapRequestPayload] GoalRequest $request): JsonResponse
    {
        $caller = $this->caller();

        $this->commandBus->dispatch(new PlanGoalCommand(
            Uuid::generate()->value(),
            $caller->value(),
            $request->name,
            $request->target,
            $request->wantedBy
        ));

        return $this->json($this->goals->of($caller), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'adjust', methods: ['PUT'])]
    #[OA\Put(path: '/api/allowance/goals/{id}', summary: 'Change what is being saved for', tags: ['Allowance'])]
    #[OA\Response(response: 200, description: 'Goal changed')]
    public function adjust(string $id, #[MapRequestPayload] GoalRequest $request): JsonResponse
    {
        $owner = $this->owner($id);

        $this->commandBus->dispatch(new AdjustGoalCommand($id, $request->name, $request->target, $request->wantedBy));

        return $this->json($this->goals->of($owner));
    }

    #[Route('/{id}/put-aside', name: 'put_aside', methods: ['POST'])]
    #[OA\Post(path: '/api/allowance/goals/{id}/put-aside', summary: 'Move money out of what is in hand and onto a goal', tags: ['Allowance'])]
    #[OA\Response(response: 200, description: 'Money put aside')]
    #[OA\Response(response: 400, description: 'There is not that much in hand')]
    public function putAside(string $id, #[MapRequestPayload] GoalAmountRequest $request): JsonResponse
    {
        $owner = $this->owner($id);

        $this->commandBus->dispatch(new PutAsideForGoalCommand($id, $request->amount));

        return $this->json($this->goals->of($owner));
    }

    #[Route('/{id}/take-back', name: 'take_back', methods: ['POST'])]
    #[OA\Post(path: '/api/allowance/goals/{id}/take-back', summary: 'Move money off a goal and back into what is in hand', tags: ['Allowance'])]
    #[OA\Response(response: 200, description: 'Money taken back')]
    public function takeBack(string $id, #[MapRequestPayload] GoalAmountRequest $request): JsonResponse
    {
        $owner = $this->owner($id);

        $this->commandBus->dispatch(new TakeBackFromGoalCommand($id, $request->amount));

        return $this->json($this->goals->of($owner));
    }

    #[Route('/{id}/spend', name: 'spend', methods: ['POST'])]
    #[OA\Post(path: '/api/allowance/goals/{id}/spend', summary: 'Buy the thing the money was put aside for', tags: ['Allowance'])]
    #[OA\Response(response: 200, description: 'Money spent off the goal')]
    public function spend(string $id, #[MapRequestPayload] GoalAmountRequest $request): JsonResponse
    {
        $owner = $this->owner($id);

        $this->commandBus->dispatch(new SpendGoalCommand($id, $request->amount, $request->description ?? ''));

        return $this->json($this->goals->of($owner));
    }

    #[Route('/{id}', name: 'close', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/allowance/goals/{id}', summary: 'Give up on a goal and take back what was put aside', tags: ['Allowance'])]
    #[OA\Response(response: 200, description: 'Goal closed')]
    public function close(string $id): JsonResponse
    {
        $owner = $this->owner($id);

        $this->commandBus->dispatch(new CloseGoalCommand($id));

        return $this->json($this->goals->of($owner));
    }

    private function owner(string $goalId): Uuid
    {
        $goal = $this->repository->find(Uuid::fromString($goalId));

        if ($goal === null) {
            throw new \DomainException('No such goal');
        }

        $caller = $this->caller();
        $this->access->assertSelf($caller, $goal->userId());

        return $caller;
    }

    private function caller(): Uuid
    {
        return $this->access->callerFrom($this->getUser()->getUserIdentifier());
    }
}
