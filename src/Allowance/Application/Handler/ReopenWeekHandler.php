<?php

declare(strict_types=1);

namespace App\Allowance\Application\Handler;

use App\Allowance\Application\Command\ReopenWeekCommand;
use App\Allowance\Domain\Repository\WeekClosureRepositoryInterface;
use App\Allowance\Domain\Service\MoneyLedger;
use App\Allowance\Domain\ValueObject\AccountRef;
use App\Allowance\Domain\ValueObject\TransactionType;
use App\Allowance\Domain\ValueObject\WeekStart;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReopenWeekHandler
{
    public function __construct(
        private WeekClosureRepositoryInterface $closures,
        private MoneyLedger $ledger
    ) {
    }

    public function __invoke(ReopenWeekCommand $command): void
    {
        $userId = Uuid::fromString($command->userId);
        $week = WeekStart::fromString($command->weekStart);
        $closure = $this->closures->find($userId, $week);

        if ($closure === null) {
            throw new \DomainException('This week is not closed');
        }

        if ($closure->total()->isPositive()) {
            $this->ledger->transfer(
                $userId,
                AccountRef::pending(),
                AccountRef::earnings(),
                $closure->total(),
                TransactionType::WEEK_REOPENED,
                sprintf('The week of %s was opened again', $week->value())
            );
        }

        $this->closures->remove($closure);
    }
}
