<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Infrastructure\Persistence;

use App\Notifications\Communication\Domain\Entity\NotificationPolicy;
use App\Notifications\Communication\Domain\Repository\NotificationPolicyRepositoryInterface;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;

final class InMemoryNotificationPolicyRepository implements NotificationPolicyRepositoryInterface
{
    /**
     * @var array<string, NotificationPolicy>
     */
    private array $policies = [];

    public function save(NotificationPolicy $policy): void
    {
        $this->policies[$policy->event()->value()] = $policy;
    }

    public function findByEvent(NotificationEvent $event): ?NotificationPolicy
    {
        return $this->policies[$event->value()] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->policies);
    }
}
