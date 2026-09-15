<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Allowance\Application\Command\RemoveAllowanceRuleCommand;
use App\Allowance\Application\Command\SetAllowanceRuleCommand;
use App\Allowance\Application\Service\AllowanceAccess;
use App\Allowance\Domain\Entity\AllowanceRule;
use App\Allowance\Domain\Repository\AllowanceRuleRepositoryInterface;
use App\Presentation\Api\Dto\Allowance\SetAllowanceRuleRequest;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Exception\UnauthorizedTeamActionException;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/allowance/rules', name: 'api_allowance_rule_')]
#[OA\Tag(name: 'Allowance')]
#[IsGranted('ROLE_USER')]
class AllowanceRuleApiController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly AllowanceRuleRepositoryInterface $rules,
        private readonly TeamMembershipRepositoryInterface $memberships,
        private readonly AllowanceAccess $access,
        private readonly string $currency
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(path: '/api/allowance/rules', summary: 'What a week of points is worth in a team', tags: ['Allowance'])]
    #[OA\Parameter(name: 'teamId', in: 'query', required: true, description: 'Team whose rules to read')]
    #[OA\Response(response: 200, description: 'One rule per points account, amounts in minor units')]
    #[OA\Response(response: 403, description: 'Caller is not a member of the team')]
    public function list(Request $request): JsonResponse
    {
        $teamId = Uuid::fromString((string) $request->query->get('teamId'));

        if (!$this->memberships->isMember($this->caller(), $teamId)) {
            throw new UnauthorizedTeamActionException('Only members read the rules of their team');
        }

        return $this->json([
            'currency' => $this->currency,
            'rules' => array_map(
                static fn (AllowanceRule $rule) => [
                    'id' => $rule->id()->value(),
                    'teamId' => $rule->teamId()->value(),
                    'pointsAccount' => $rule->pointsAccount()->value,
                    'minimumPoints' => $rule->minimumPoints(),
                    'rateAmount' => $rule->rate()->amount()->minorUnits(),
                    'ratePerPoints' => $rule->rate()->perPoints(),
                    'isActive' => $rule->isActive(),
                ],
                $this->rules->ofTeam($teamId)
            ),
        ]);
    }

    #[Route('', name: 'set', methods: ['PUT'])]
    #[OA\Put(path: '/api/allowance/rules', summary: 'Set what one points account pays (Admin only)', tags: ['Allowance'])]
    #[OA\Response(response: 200, description: 'Rule saved')]
    #[OA\Response(response: 403, description: 'Caller does not administer the team')]
    public function set(#[MapRequestPayload] SetAllowanceRuleRequest $request): JsonResponse
    {
        $this->assertAdmin(Uuid::fromString($request->teamId));

        $this->commandBus->dispatch(new SetAllowanceRuleCommand(
            $request->teamId,
            $request->pointsAccount,
            $request->minimumPoints,
            $request->rateAmount,
            $request->ratePerPoints
        ));

        return $this->json(['message' => 'Rule saved']);
    }

    #[Route('/{pointsAccount}', name: 'remove', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/allowance/rules/{pointsAccount}', summary: 'Stop paying for one points account (Admin only)', tags: ['Allowance'])]
    #[OA\Parameter(name: 'teamId', in: 'query', required: true, description: 'Team the rule belongs to')]
    #[OA\Response(response: 200, description: 'Rule removed')]
    public function remove(string $pointsAccount, Request $request): JsonResponse
    {
        $teamId = Uuid::fromString((string) $request->query->get('teamId'));
        $this->assertAdmin($teamId);

        $this->commandBus->dispatch(new RemoveAllowanceRuleCommand($teamId->value(), $pointsAccount));

        return $this->json(['message' => 'Rule removed']);
    }

    private function assertAdmin(Uuid $teamId): void
    {
        if (!$this->memberships->isAdmin($this->caller(), $teamId)) {
            throw new UnauthorizedTeamActionException('Only team admins decide what points are worth');
        }
    }

    private function caller(): Uuid
    {
        return $this->access->callerFrom($this->getUser()->getUserIdentifier());
    }
}
