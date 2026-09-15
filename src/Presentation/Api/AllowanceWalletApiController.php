<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Allowance\Application\Command\CancelPayoutCommand;
use App\Allowance\Application\Command\ConfirmPayoutCommand;
use App\Allowance\Application\Command\OfferPayoutCommand;
use App\Allowance\Application\Command\RecordExpenseCommand;
use App\Allowance\Application\Command\RecordIncomeCommand;
use App\Allowance\Application\Service\AllowanceAccess;
use App\Allowance\Application\Service\LedgerView;
use App\Allowance\Application\Service\WalletView;
use App\Allowance\Domain\Entity\Payout;
use App\Allowance\Domain\Repository\PayoutRepositoryInterface;
use App\Presentation\Api\Dto\Allowance\BookingRequest;
use App\Presentation\Api\Dto\Allowance\OfferPayoutRequest;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/allowance', name: 'api_allowance_wallet_')]
#[OA\Tag(name: 'Allowance')]
#[IsGranted('ROLE_USER')]
class AllowanceWalletApiController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly WalletView $wallet,
        private readonly LedgerView $ledger,
        private readonly PayoutRepositoryInterface $payouts,
        private readonly AllowanceAccess $access
    ) {
    }

    #[Route('/wallet', name: 'show', methods: ['GET'])]
    #[OA\Get(path: '/api/allowance/wallet', summary: 'Money waiting to be paid out and money already in hand', tags: ['Allowance'])]
    #[OA\Parameter(name: 'userId', in: 'query', required: false, description: 'Member to look at; defaults to the caller')]
    #[OA\Response(response: 200, description: 'Balances in minor units, with the payouts still to be confirmed')]
    public function show(Request $request): JsonResponse
    {
        return $this->json($this->wallet->of($this->inspected($request)));
    }

    #[Route('/ledger', name: 'ledger', methods: ['GET'])]
    #[OA\Get(path: '/api/allowance/ledger', summary: 'Everything that happened to the money', tags: ['Allowance'])]
    #[OA\Parameter(name: 'userId', in: 'query', required: false, description: 'Member to look at; defaults to the caller')]
    #[OA\Parameter(name: 'from', in: 'query', required: false, description: 'Oldest day to include (Y-m-d)')]
    #[OA\Parameter(name: 'to', in: 'query', required: false, description: 'Day to stop before (Y-m-d)')]
    #[OA\Response(response: 200, description: 'Bookings, newest first')]
    public function ledger(Request $request): JsonResponse
    {
        return $this->json($this->ledger->of(
            $this->inspected($request),
            $this->day($request->query->get('from')),
            $this->day($request->query->get('to')),
            min(500, max(1, $request->query->getInt('limit', 100)))
        ));
    }

    #[Route('/income', name: 'income', methods: ['POST'])]
    #[OA\Post(path: '/api/allowance/income', summary: 'Book money that came from somewhere else', tags: ['Allowance'])]
    #[OA\Response(response: 201, description: 'Income booked')]
    public function income(#[MapRequestPayload] BookingRequest $request): JsonResponse
    {
        $caller = $this->caller();

        $this->commandBus->dispatch(new RecordIncomeCommand(
            Uuid::generate()->value(),
            $caller->value(),
            $request->amount,
            $request->description,
            $request->on
        ));

        return $this->json($this->wallet->of($caller), Response::HTTP_CREATED);
    }

    #[Route('/expenses', name: 'expense', methods: ['POST'])]
    #[OA\Post(path: '/api/allowance/expenses', summary: 'Book something that was bought', tags: ['Allowance'])]
    #[OA\Response(response: 201, description: 'Expense booked')]
    #[OA\Response(response: 400, description: 'There is not enough money to spend')]
    public function expense(#[MapRequestPayload] BookingRequest $request): JsonResponse
    {
        $caller = $this->caller();

        $this->commandBus->dispatch(new RecordExpenseCommand(
            Uuid::generate()->value(),
            $caller->value(),
            $request->amount,
            $request->description,
            $request->on
        ));

        return $this->json($this->wallet->of($caller), Response::HTTP_CREATED);
    }

    #[Route('/payouts', name: 'payouts', methods: ['GET'])]
    #[OA\Get(path: '/api/allowance/payouts', summary: 'Payouts offered, confirmed and called off', tags: ['Allowance'])]
    #[OA\Parameter(name: 'userId', in: 'query', required: false, description: 'Member to look at; defaults to the caller')]
    #[OA\Response(response: 200, description: 'Payouts, newest first')]
    public function payouts(Request $request): JsonResponse
    {
        return $this->json([
            'payouts' => array_map(
                static fn (Payout $payout) => [
                    'id' => $payout->id()->value(),
                    'amount' => $payout->amount()->minorUnits(),
                    'status' => $payout->status()->value,
                    'note' => $payout->note(),
                    'offeredAt' => $payout->offeredAt()->format('c'),
                    'settledAt' => $payout->settledAt()?->format('c'),
                ],
                $this->payouts->ofUser($this->inspected($request))
            ),
        ]);
    }

    #[Route('/payouts', name: 'offer_payout', methods: ['POST'])]
    #[OA\Post(path: '/api/allowance/payouts', summary: 'Hand over all or part of what is waiting (Admin only)', tags: ['Allowance'])]
    #[OA\Response(response: 201, description: 'Payout offered, waiting for the member to confirm')]
    #[OA\Response(response: 400, description: 'Less than that is waiting to be paid out')]
    public function offerPayout(#[MapRequestPayload] OfferPayoutRequest $request): JsonResponse
    {
        $caller = $this->caller();
        $member = Uuid::fromString($request->userId);
        $this->access->adminTeamFor($caller, $member);

        $this->commandBus->dispatch(new OfferPayoutCommand(
            Uuid::generate()->value(),
            $member->value(),
            $request->amount,
            $caller->value(),
            $request->note
        ));

        return $this->json($this->wallet->of($member), Response::HTTP_CREATED);
    }

    #[Route('/payouts/{id}/confirm', name: 'confirm_payout', methods: ['POST'])]
    #[OA\Post(path: '/api/allowance/payouts/{id}/confirm', summary: 'Confirm the money was really handed over', tags: ['Allowance'])]
    #[OA\Response(response: 200, description: 'Money moved to what is in hand')]
    #[OA\Response(response: 400, description: 'No such payout is waiting')]
    public function confirmPayout(string $id): JsonResponse
    {
        $caller = $this->caller();

        $this->commandBus->dispatch(new ConfirmPayoutCommand($id, $caller->value()));

        return $this->json($this->wallet->of($caller));
    }

    #[Route('/payouts/{id}/cancel', name: 'cancel_payout', methods: ['POST'])]
    #[OA\Post(path: '/api/allowance/payouts/{id}/cancel', summary: 'Call off a payout nobody confirmed (Admin only)', tags: ['Allowance'])]
    #[OA\Response(response: 200, description: 'Payout called off')]
    public function cancelPayout(string $id): JsonResponse
    {
        $payout = $this->payouts->find(Uuid::fromString($id));

        if ($payout === null) {
            throw new \DomainException('No such payout');
        }

        $this->access->adminTeamFor($this->caller(), $payout->userId());

        $this->commandBus->dispatch(new CancelPayoutCommand($id));

        return $this->json($this->wallet->of($payout->userId()));
    }

    private function inspected(Request $request): Uuid
    {
        return $this->access->inspected($this->caller(), $request->query->get('userId'));
    }

    private function day(?string $day): ?DateTimeImmutable
    {
        if ($day === null || $day === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $day);

        if ($parsed === false) {
            throw new \DomainException('A day is given as YYYY-MM-DD');
        }

        return $parsed;
    }

    private function caller(): Uuid
    {
        return $this->access->callerFrom($this->getUser()->getUserIdentifier());
    }
}
