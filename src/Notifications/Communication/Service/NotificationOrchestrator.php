<?php

declare(strict_types=1);

namespace App\Notifications\Communication\Service;

use App\Notifications\Application\Service\NotificationFacade;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserSettings\Domain\Repository\UserSettingsRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service responsible for orchestrating notification sending based on user preferences
 */
final readonly class NotificationOrchestrator
{
    public function __construct(
        private NotificationFacade $notificationFacade,
        private UserRepositoryInterface $userRepository,
        private UserSettingsRepositoryInterface $userSettingsRepository,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Send notification to a user based on their preferences
     */
    public function notifyUser(
        Uuid $userId,
        string $message,
        ?string $subject = null,
        array $additionalParameters = []
    ): void {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            $this->logger?->warning('User not found for notification', ['user_id' => $userId->value()]);
            return;
        }

        $settings = $this->userSettingsRepository->findByUserId($userId);
        $enabledChannels = $settings?->notificationPreferences()->getEnabledChannels() ?? ['email'];

        if (empty($enabledChannels)) {
            $this->logger?->info('No notification channels enabled for user', ['user_id' => $userId->value()]);
            return;
        }

        foreach ($enabledChannels as $channel) {
            try {
                if ($channel === 'email') {
                    $this->notificationFacade->sendEmail(
                        $user->email()->value(),
                        $message,
                        $subject,
                        $additionalParameters
                    );
                } elseif ($channel === 'sms') {
                    // For SMS, we would need a phone number field on the user
                    // For now, we'll log this as not implemented
                    $this->logger?->info('SMS notifications not fully implemented', [
                        'user_id' => $userId->value()
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger?->error('Failed to send notification', [
                    'user_id' => $userId->value(),
                    'channel' => $channel,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
