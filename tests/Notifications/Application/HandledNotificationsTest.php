<?php

declare(strict_types=1);

namespace App\Tests\Notifications\Application;

use App\Allowance\Domain\Event\PayoutSettled;
use App\Notifications\Application\Service\HandledNotifications;
use App\Notifications\Communication\EventSubscriber\ResolveHandledNotificationsSubscriber;
use App\Notifications\Domain\Entity\InAppNotification;
use App\Notifications\Infrastructure\Persistence\InMemoryInAppNotificationRepository;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\TaskManagement\Domain\Event\TaskExecutionApproved;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class HandledNotificationsTest extends TestCase
{
    private InMemoryInAppNotificationRepository $notifications;

    private ResolveHandledNotificationsSubscriber $subscriber;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-09-25 18:00:00');
        $clock = new FixedClock($this->now);
        $this->notifications = new InMemoryInAppNotificationRepository($clock);
        $this->subscriber = new ResolveHandledNotificationsSubscriber(new HandledNotifications($this->notifications, $clock));
    }

    public function testOnceOneParentApprovesTheOtherHasNothingWaiting(): void
    {
        $executionId = Uuid::generate();
        $mum = $this->given(Uuid::generate(), 'task_completed', 'task-' . $executionId->value());
        $dad = $this->given(Uuid::generate(), 'task_completed', 'task-' . $executionId->value());

        $this->subscriber->taskHandled(new TaskExecutionApproved($executionId, Uuid::generate(), $this->now));

        $this->assertTrue($mum->isResolved());
        $this->assertTrue($dad->isResolved());
        $this->assertSame(0, $this->notifications->countUnreadFor($mum->userId()));
    }

    public function testOtherNewsAboutTheSameTaskStaysOpen(): void
    {
        $executionId = Uuid::generate();
        $assigned = $this->given(Uuid::generate(), 'task_assigned', 'task-' . $executionId->value());
        $otherTask = $this->given(Uuid::generate(), 'task_completed', 'task-' . Uuid::generate()->value());

        $this->subscriber->taskHandled(new TaskExecutionApproved($executionId, Uuid::generate(), $this->now));

        $this->assertFalse($assigned->isResolved());
        $this->assertFalse($otherTask->isResolved());
    }

    public function testASettledPayoutStopsWaiting(): void
    {
        $payoutId = Uuid::generate();
        $child = Uuid::generate();
        $offer = $this->given($child, 'payout_offered', 'payout-' . $payoutId->value());

        $this->subscriber->payoutSettled(new PayoutSettled($payoutId, $child, PayoutSettled::CONFIRMED, $this->now));

        $this->assertTrue($offer->isResolved());
    }

    private function given(Uuid $userId, string $event, string $tag): InAppNotification
    {
        $notification = InAppNotification::raise(Uuid::generate(), $userId, 'Coś czeka', null, ['event' => $event, 'tag' => $tag], $this->now->modify('-1 hour'));
        $this->notifications->save($notification);

        return $notification;
    }
}
