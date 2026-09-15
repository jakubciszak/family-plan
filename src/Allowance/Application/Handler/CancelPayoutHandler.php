<?php

declare(strict_types=1);

namespace App\Allowance\Application\Handler;

use App\Allowance\Application\Command\CancelPayoutCommand;
use App\Allowance\Domain\Repository\PayoutRepositoryInterface;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CancelPayoutHandler
{
    public function __construct(
        private PayoutRepositoryInterface $payouts,
        private ClockInterface $clock
    ) {
    }

    public function __invoke(CancelPayoutCommand $command): void
    {
        $payout = $this->payouts->find(Uuid::fromString($command->payoutId));

        if ($payout === null) {
            throw new \DomainException('No such payout');
        }

        $payout->cancel($this->clock);

        $this->payouts->save($payout);
    }
}
