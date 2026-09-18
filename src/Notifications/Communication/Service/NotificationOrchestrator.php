<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Service;

use App\Notifications\Application\Service\NotificationFacade;
use App\Notifications\Communication\Application\Service\NotificationPolicyProvider;
use App\Notifications\Communication\Domain\Service\ChannelResolver;
use App\Notifications\Communication\Domain\ValueObject\NotificationChannels;
use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface;
use App\UserSettings\Domain\ValueObject\PreferenceOption;
use App\UserSettings\Domain\ValueObject\PreferenceType;
use Psr\Log\LoggerInterface;

final readonly class NotificationOrchestrator
{
    public function __construct(
        private NotificationFacade $notificationFacade,
        private UserRepositoryInterface $userRepository,
        private UserSettingsRepositoryInterface $userSettingsRepository,
        private NotificationPolicyProvider $policyProvider,
        private ChannelResolver $channelResolver,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function notifyUser(
        NotificationEvent $event,
        Uuid $userId,
        string $message,
        ?string $subject = null,
        array $additionalParameters = []
    ): void {
        $policyChannels = $this->policyProvider->channelsFor($event);

        if ($policyChannels->isEmpty()) {
            $this->logger?->info('Notification event is switched off', ['event' => $event->value()]);

            return;
        }

        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            $this->logger?->warning('User not found for notification', ['user_id' => $userId->value()]);

            return;
        }

        $channels = $this->channelResolver->resolve($policyChannels, $this->userChannelSettings($userId));

        if ($channels->isEmpty()) {
            $this->logger?->info('No notification channels enabled for user', [
                'user_id' => $userId->value(),
                'event' => $event->value(),
            ]);

            return;
        }

        $additionalParameters['delivery_channels'] = $channels->toArray();

        foreach ($channels->toArray() as $channel) {
            try {
                match ($channel) {
                    NotificationChannels::EMAIL => $this->notificationFacade->sendEmail(
                        $user->email()->value(),
                        $message,
                        $subject,
                        $additionalParameters
                    ),
                    NotificationChannels::IN_APP => $this->notificationFacade->sendInApp(
                        $userId->value(),
                        $message,
                        $subject,
                        $additionalParameters
                    ),
                    NotificationChannels::PUSH => $this->notificationFacade->sendPush(
                        $userId->value(),
                        $message,
                        $subject,
                        $additionalParameters
                    ),
                    NotificationChannels::SMS => $this->logger?->info('SMS notifications not fully implemented', [
                        'user_id' => $userId->value(),
                    ]),
                };
            } catch (\Throwable $e) {
                $this->logger?->error('Failed to send notification', [
                    'user_id' => $userId->value(),
                    'event' => $event->value(),
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function notifyEmail(
        string $email,
        string $message,
        ?string $subject = null,
        array $additionalParameters = []
    ): void {
        try {
            $this->notificationFacade->sendEmail(
                $email,
                $message,
                $subject,
                $additionalParameters
            );
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to send email notification', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, bool>
     */
    private function userChannelSettings(Uuid $userId): array
    {
        $preference = $this->userSettingsRepository
            ->findByUserId($userId)
            ?->getPreferenceByType(PreferenceType::notifications());

        $settings = [];

        foreach ($preference?->options() ?? [] as $option) {
            /** @var PreferenceOption $option */
            $settings[$option->name()] = $option->isEnabled();
        }

        return $settings;
    }
}
