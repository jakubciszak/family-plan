<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Domain\Port\NativePushSenderInterface;
use App\Notifications\Domain\Repository\NativePushDeviceRepositoryInterface;
use App\Notifications\Domain\Repository\PushSubscriptionRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * How many devices a push reaches: browsers with Web Push, plus phones with the app once FCM is configured.
 */
final readonly class PushReach
{
    public function __construct(
        private PushSubscriptionRepositoryInterface $subscriptions,
        private ?NativePushDeviceRepositoryInterface $devices = null,
        private ?NativePushSenderInterface $nativeSender = null
    ) {
    }

    public function devicesOf(Uuid $userId): int
    {
        $phones = $this->devices !== null && $this->nativeSender?->isConfigured()
            ? $this->devices->countForUser($userId)
            : 0;

        return $this->subscriptions->countForUser($userId) + $phones;
    }
}
