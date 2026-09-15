<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Push;

use App\Notifications\Domain\Entity\PushSubscription;
use App\Notifications\Domain\Port\PushDelivery;
use App\Notifications\Domain\Port\PushSenderInterface;
use App\Notifications\Domain\ValueObject\NotificationMessage;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

final class MinishlinkPushSender implements PushSenderInterface
{
    private ?WebPush $webPush = null;

    public function __construct(
        private readonly string $publicKey,
        private readonly string $privateKey,
        private readonly string $subject,
        private readonly string $applicationUrl,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function send(PushSubscription $subscription, NotificationMessage $message): PushDelivery
    {
        if (!$this->isConfigured()) {
            $this->logger?->info('Push notifications are switched off — no VAPID keys configured');

            return PushDelivery::Failed;
        }

        try {
            $report = $this->webPush()->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint(),
                    'publicKey' => $subscription->publicKey(),
                    'authToken' => $subscription->authToken(),
                ]),
                $this->payload($message)
            );
        } catch (\Throwable $exception) {
            $this->logger?->error('Push notification could not be sent', [
                'endpoint' => $subscription->endpoint(),
                'error' => $exception->getMessage(),
            ]);

            return PushDelivery::Failed;
        }

        if ($report->isSuccess()) {
            return PushDelivery::Delivered;
        }

        if ($report->isSubscriptionExpired()) {
            return PushDelivery::Gone;
        }

        $this->logger?->warning('Push service rejected a notification', [
            'endpoint' => $subscription->endpoint(),
            'reason' => $report->getReason(),
        ]);

        return PushDelivery::Failed;
    }

    public function isConfigured(): bool
    {
        return trim($this->publicKey) !== '' && trim($this->privateKey) !== '';
    }

    private function payload(NotificationMessage $message): string
    {
        $parameters = $message->additionalParameters();

        return json_encode([
            'title' => $message->subject() ?? 'Family Plan',
            'body' => $message->content(),
            'url' => is_string($parameters['url'] ?? null) ? $parameters['url'] : $this->applicationUrl,
            'tag' => is_string($parameters['tag'] ?? null) ? $parameters['tag'] : null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function webPush(): WebPush
    {
        return $this->webPush ??= new WebPush([
            'VAPID' => [
                'subject' => $this->subject,
                'publicKey' => $this->publicKey,
                'privateKey' => $this->privateKey,
            ],
        ]);
    }
}
