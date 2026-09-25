<?php

declare(strict_types=1);

namespace App\Notifications\Communication\EventSubscriber;

use App\Allowance\Domain\Event\PayoutSettled;
use App\Notifications\Application\Service\HandledNotifications;
use App\TaskManagement\Domain\Event\TaskExecutionApproved;
use App\TaskManagement\Domain\Event\TaskExecutionRejected;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class ResolveHandledNotificationsSubscriber implements EventSubscriberInterface
{
    public function __construct(private HandledNotifications $handled)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TaskExecutionApproved::class => 'taskHandled',
            TaskExecutionRejected::class => 'taskHandled',
            PayoutSettled::class => 'payoutSettled',
        ];
    }

    public function taskHandled(TaskExecutionApproved|TaskExecutionRejected $event): void
    {
        $this->handled->approvalHandled($event->executionId());
    }

    public function payoutSettled(PayoutSettled $event): void
    {
        $this->handled->payoutSettled($event->payoutId());
    }
}
