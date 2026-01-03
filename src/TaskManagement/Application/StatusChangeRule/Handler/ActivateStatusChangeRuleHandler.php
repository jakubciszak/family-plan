<?php

declare(strict_types=1);

namespace App\TaskManagement\Application\StatusChangeRule\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Application\StatusChangeRule\Command\ActivateStatusChangeRuleCommand;
use App\TaskManagement\Domain\Repository\StatusChangeRuleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ActivateStatusChangeRuleHandler
{
    public function __construct(
        private StatusChangeRuleRepositoryInterface $repository
    ) {
    }

    public function __invoke(ActivateStatusChangeRuleCommand $command): void
    {
        $rule = $this->repository->findById(Uuid::fromString($command->id));

        if (!$rule) {
            throw new \DomainException('Status change rule not found');
        }

        $rule->activate();
        $this->repository->save($rule);
    }
}
