<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Application\Service;

use App\Notifications\Communication\Domain\Repository\NotificationPolicyRepositoryInterface;
use App\Notifications\Communication\Domain\ValueObject\NotificationChannels;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;

final readonly class NotificationPolicyProvider
{
    public function __construct(
        private NotificationPolicyRepositoryInterface $repository
    ) {
    }

    public function channelsFor(NotificationEvent $event): NotificationChannels
    {
        if (!$event->isConfigurable()) {
            return $event->defaultChannels();
        }

        return $this->repository->findByEvent($event)?->channels() ?? $event->defaultChannels();
    }

    /**
     * @return array<string, NotificationChannels>
     */
    public function matrix(): array
    {
        $matrix = [];

        foreach (NotificationEvent::all() as $event) {
            $matrix[$event->value()] = $this->channelsFor($event);
        }

        return $matrix;
    }
}
