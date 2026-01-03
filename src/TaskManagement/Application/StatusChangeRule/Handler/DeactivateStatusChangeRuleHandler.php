<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\StatusChangeRule\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\StatusChangeRule\Command\DeactivateStatusChangeRuleCommand;
use App\TaskManagement\Domain\Repository\StatusChangeRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeactivateStatusChangeRuleHandler
{
    public function __construct(
        private StatusChangeRuleRepositoryInterface $repository
    ) {
    }

    public function __invoke(DeactivateStatusChangeRuleCommand $command): void
    {
        $rule = $this->repository->findById(Uuid::fromString($command->id));

        if (!$rule) {
            throw new \DomainException('Status change rule not found');
        }

        $rule->deactivate();
        $this->repository->save($rule);
    }
}
