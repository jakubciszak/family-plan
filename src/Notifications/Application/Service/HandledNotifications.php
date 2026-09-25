<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Domain\Repository\InAppNotificationRepositoryInterface;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;

/**
 * Once somebody handles what a notification asked for, it stops waiting on every device:
 * the other parent no longer sees "waiting for approval" for a task that is already approved.
 */
final readonly class HandledNotifications
{
    public function __construct(
        private InAppNotificationRepositoryInterface $notifications,
        private ClockInterface $clock
    ) {
    }

    public function approvalHandled(Uuid $executionId): int
    {
        return $this->notifications->resolveTopic('task-' . $executionId->value(), $this->clock->now(), NotificationEvent::TASK_COMPLETED);
    }

    public function payoutSettled(Uuid $payoutId): int
    {
        return $this->notifications->resolveTopic('payout-' . $payoutId->value(), $this->clock->now(), NotificationEvent::PAYOUT_OFFERED);
    }
}
