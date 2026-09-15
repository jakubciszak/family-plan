<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Allowance\Application\Command\CloseWeekCommand;
use App\Allowance\Application\Command\ReopenWeekCommand;
use App\Allowance\Application\Service\AllowanceAccess;
use App\Allowance\Application\Service\AllowanceWeekView;
use App\Allowance\Domain\ValueObject\WeekStart;
use App\Presentation\Api\Dto\Allowance\CloseWeekRequest;
use App\Shared\Domain\ValueObject\Uuid;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/allowance/weeks', name: 'api_allowance_week_')]
#[OA\Tag(name: 'Allowance')]
#[IsGranted('ROLE_USER')]
class AllowanceWeekApiController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly AllowanceWeekView $weeks,
        private readonly AllowanceAccess $access
    ) {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    #[OA\Get(path: '/api/allowance/weeks', summary: 'A week of points and what it earns', tags: ['Allowance'])]
    #[OA\Parameter(name: 'weekStart', in: 'query', required: false, description: 'Any day of the wanted week (Y-m-d)')]
    #[OA\Parameter(name: 'userId', in: 'query', required: false, description: 'Member to look at; defaults to the caller')]
    #[OA\Response(response: 200, description: 'Points per day, the amount expected and the closure if there is one')]
    #[OA\Response(response: 403, description: 'Caller may not look at this member')]
    public function show(Request $request): JsonResponse
    {
        $userId = $this->access->inspected($this->caller(), $request->query->get('userId'));
        $week = $this->weekFrom($request->query->get('weekStart'));

        return $this->json($this->weeks->of($userId, $week));
    }

    #[Route('/close', name: 'close', methods: ['POST'])]
    #[OA\Post(path: '/api/allowance/weeks/close', summary: 'Close a week and book what it earned as waiting to be paid out (Admin only)', tags: ['Allowance'])]
    #[OA\Response(response: 200, description: 'Week closed')]
    #[OA\Response(response: 400, description: 'The week is already closed or has not started')]
    #[OA\Response(response: 403, description: 'Caller does not administer this member')]
    public function close(#[MapRequestPayload] CloseWeekRequest $request): JsonResponse
    {
        $caller = $this->caller();
        $member = Uuid::fromString($request->userId);
        $teamId = $this->access->adminTeamFor($caller, $member);

        $this->commandBus->dispatch(new CloseWeekCommand(
            $teamId->value(),
            $member->value(),
            $request->weekStart,
            $caller->value()
        ));

        return $this->json($this->weeks->of($member, WeekStart::fromString($request->weekStart)));
    }

    #[Route('/reopen', name: 'reopen', methods: ['POST'])]
    #[OA\Post(path: '/api/allowance/weeks/reopen', summary: 'Open a closed week again and take the money back off the waiting account (Admin only)', tags: ['Allowance'])]
    #[OA\Response(response: 200, description: 'Week opened again')]
    #[OA\Response(response: 400, description: 'The week is not closed, or the money has already been paid out')]
    public function reopen(#[MapRequestPayload] CloseWeekRequest $request): JsonResponse
    {
        $caller = $this->caller();
        $member = Uuid::fromString($request->userId);
        $this->access->adminTeamFor($caller, $member);

        $this->commandBus->dispatch(new ReopenWeekCommand($member->value(), $request->weekStart));

        return $this->json($this->weeks->of($member, WeekStart::fromString($request->weekStart)));
    }

    private function weekFrom(?string $day): WeekStart
    {
        return $day === null || $day === ''
            ? WeekStart::of(new \DateTimeImmutable())
            : WeekStart::fromString($day);
    }

    private function caller(): Uuid
    {
        return $this->access->callerFrom($this->getUser()->getUserIdentifier());
    }
}
