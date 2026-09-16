<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Repository;

use App\Notifications\Domain\Entity\PushSubscription;
use App\Shared\Domain\ValueObject\Uuid;

interface PushSubscriptionRepositoryInterface
{
    public function save(PushSubscription $subscription): void;

    public function delete(PushSubscription $subscription): void;

    public function findByEndpoint(string $endpoint): ?PushSubscription;

    /**
     * @return PushSubscription[]
     */
    public function findForUser(Uuid $userId): array;

    public function countForUser(Uuid $userId): int;

}
