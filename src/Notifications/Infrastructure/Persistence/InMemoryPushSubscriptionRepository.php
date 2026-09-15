<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence;

use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\Repository\PushSubscriptionRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;

final class InMemoryPushSubscriptionRepository implements PushSubscriptionRepositoryInterface
{
    /**
     * @var array<string, PushSubscription>
     */
    private array $subscriptions = [];

    public function save(PushSubscription $subscription): void
    {
        $this->subscriptions[$subscription->id()->value()] = $subscription;
    }

    public function delete(PushSubscription $subscription): void
    {
        unset($this->subscriptions[$subscription->id()->value()]);
    }

    public function findByEndpoint(string $endpoint): ?PushSubscription
    {
        foreach ($this->subscriptions as $subscription) {
            if ($subscription->endpoint() === $endpoint) {
                return $subscription;
            }
        }

        return null;
    }

    public function findForUser(Uuid $userId): array
    {
        return array_values(array_filter(
            $this->subscriptions,
            static fn (PushSubscription $subscription) => $subscription->belongsTo($userId)
        ));
    }

    public function countForUser(Uuid $userId): int
    {
        return count($this->findForUser($userId));
    }
}
