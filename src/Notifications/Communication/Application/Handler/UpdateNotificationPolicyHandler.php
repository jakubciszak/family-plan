<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Application\Handler;

use App\Notifications\Communication\Application\Command\UpdateNotificationPolicyCommand;
use App\Notifications\Communication\Domain\Entity\NotificationPolicy;
use App\Notifications\Communication\Domain\Repository\NotificationPolicyRepositoryInterface;
use App\Notifications\Communication\Domain\ValueObject\NotificationChannels;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateNotificationPolicyHandler
{
    public function __construct(
        private NotificationPolicyRepositoryInterface $repository
    ) {
    }

    public function __invoke(UpdateNotificationPolicyCommand $command): void
    {
        $event = NotificationEvent::fromString($command->event);

        if (!$event->isConfigurable()) {
            throw new \InvalidArgumentException(
                sprintf('Notification event "%s" is transactional and cannot be configured', $event->value())
            );
        }

        $channels = NotificationChannels::fromArray($command->channels);

        $policy = $this->repository->findByEvent($event);

        if ($policy === null) {
            $this->repository->save(NotificationPolicy::create(Uuid::generate(), $event, $channels));

            return;
        }

        $policy->changeChannels($channels);
        $this->repository->save($policy);
    }
}
