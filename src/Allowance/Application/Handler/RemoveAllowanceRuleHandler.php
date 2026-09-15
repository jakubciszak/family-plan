<?php

declare(strict_types=1);

namespace App\Allowance\Application\Handler;

use App\Allowance\Application\Command\RemoveAllowanceRuleCommand;
use App\Allowance\Domain\Repository\AllowanceRuleRepositoryInterface;
use App\PointsManagement\Domain\ValueObject\AccountKind as PointsAccountKind;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveAllowanceRuleHandler
{
    public function __construct(private AllowanceRuleRepositoryInterface $rules)
    {
    }

    public function __invoke(RemoveAllowanceRuleCommand $command): void
    {
        $rule = $this->rules->find(
            Uuid::fromString($command->teamId),
            PointsAccountKind::from($command->pointsAccount)
        );

        if ($rule !== null) {
            $this->rules->remove($rule);
        }
    }
}
