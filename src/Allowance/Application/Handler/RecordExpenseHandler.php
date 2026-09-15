<?php

declare(strict_types=1);

namespace App\Allowance\Application\Handler;

use App\Allowance\Application\Command\RecordExpenseCommand;
use App\Allowance\Application\Service\BookingDay;
use App\Allowance\Domain\Service\MoneyLedger;
use App\Allowance\Domain\ValueObject\AccountRef;
use App\Allowance\Domain\ValueObject\Money;
use App\Allowance\Domain\ValueObject\TransactionType;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordExpenseHandler
{
    public function __construct(private MoneyLedger $ledger)
    {
    }

    public function __invoke(RecordExpenseCommand $command): void
    {
        $this->ledger->transfer(
            Uuid::fromString($command->userId),
            AccountRef::available(),
            AccountRef::expenses(),
            Money::fromMinorUnits($command->amount),
            TransactionType::EXPENSE,
            $command->description,
            Uuid::fromString($command->id),
            BookingDay::from($command->on)
        );
    }
}
