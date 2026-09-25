<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Domain\Port\PushRetractionInterface;
use App\Notifications\Domain\Repository\InAppNotificationRepositoryInterface;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Once somebody handles what a notification asked for, it stops waiting on every device:
 * the other parent no longer sees "waiting for approval" for a task that is already approved,
 * neither in the app nor in the tray of their phone.
 */
final readonly class HandledNotifications
{
    public function __construct(
        private InAppNotificationRepositoryInterface $notifications,
        private ClockInterface $clock,
        private ?PushRetractionInterface $retraction = null
    ) {
    }

    /**
     * @return int how many recipients had the request waiting
     */
    public function approvalHandled(Uuid $executionId): int
    {
        return $this->resolve('task-' . $executionId->value(), NotificationEvent::TASK_COMPLETED);
    }

    /**
     * @return int how many recipients had the offer waiting
     */
    public function payoutSettled(Uuid $payoutId): int
    {
        return $this->resolve('payout-' . $payoutId->value(), NotificationEvent::PAYOUT_OFFERED);
    }

    private function resolve(string $topic, string $event): int
    {
        $recipients = $this->notifications->resolveTopic($topic, $this->clock->now(), $event);

        if ($recipients !== []) {
            $this->retraction?->retract($recipients, [$topic]);
        }

        return count($recipients);
    }
}
