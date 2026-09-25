<?php

declare(strict_types=1);

namespace App\Notifications\Application\Handler;

use App\Notifications\Application\Command\RetractPushCommand;
use App\Notifications\Domain\Port\NativePushSenderInterface;
use App\Notifications\Domain\Port\PushDelivery;
use App\Notifications\Domain\Repository\NativePushDeviceRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Only phones get the retraction. A browser has to show something for every push it receives, so web
 * notifications close when the app is next opened instead.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RetractPushHandler
{
    /** FCM carries up to 4 KB of data, and a tag has up to 120 characters. */
    private const TAGS_PER_MESSAGE = 25;

    public function __construct(
        private NativePushDeviceRepositoryInterface $devices,
        private NativePushSenderInterface $sender
    ) {
    }

    public function __invoke(RetractPushCommand $command): void
    {
        if ($command->tags === [] || !$this->sender->isConfigured()) {
            return;
        }

        foreach ($command->userIds as $userId) {
            if (!Uuid::isValid($userId)) {
                continue;
            }

            foreach ($this->devices->findForUser(Uuid::fromString($userId)) as $device) {
                foreach (array_chunk($command->tags, self::TAGS_PER_MESSAGE) as $tags) {
                    if ($this->sender->retract($device, $tags) === PushDelivery::Gone) {
                        $this->devices->delete($device);

                        continue 2;
                    }
                }
            }
        }
    }
}
