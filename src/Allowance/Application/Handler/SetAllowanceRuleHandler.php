<?php

declare(strict_types=1);

namespace App\Allowance\Application\Handler;

use App\Allowance\Application\Command\SetAllowanceRuleCommand;
use App\Allowance\Domain\Entity\AllowanceRule;
use App\Allowance\Domain\Repository\AllowanceRuleRepositoryInterface;
use App\Allowance\Domain\ValueObject\ConversionRate;
use App\Allowance\Domain\ValueObject\Money;
use App\PointsManagement\Domain\ValueObject\AccountKind as PointsAccountKind;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetAllowanceRuleHandler
{
    public function __construct(
        private AllowanceRuleRepositoryInterface $rules,
        private ClockInterface $clock
    ) {
    }

    public function __invoke(SetAllowanceRuleCommand $command): void
    {
        $teamId = Uuid::fromString($command->teamId);
        $pointsAccount = PointsAccountKind::from($command->pointsAccount);
        $rate = ConversionRate::of(Money::fromMinorUnits($command->rateAmount), $command->ratePerPoints);

        $rule = $this->rules->find($teamId, $pointsAccount);

        if ($rule === null) {
            $rule = AllowanceRule::define(
                Uuid::generate(),
                $teamId,
                $pointsAccount,
                $command->minimumPoints,
                $rate,
                $this->clock
            );
        } else {
            $rule->adjust($command->minimumPoints, $rate, $this->clock);
            $rule->activate($this->clock);
        }

        $this->rules->save($rule);
    }
}
