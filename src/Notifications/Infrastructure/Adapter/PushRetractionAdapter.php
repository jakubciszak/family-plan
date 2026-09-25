<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Adapter;

use App\Notifications\Application\Command\RetractPushCommand;
use App\Notifications\Domain\Port\PushRetractionInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Hands the retraction over to the queue, like every push: a round trip per phone does not belong in a request.
 */
final readonly class PushRetractionAdapter implements PushRetractionInterface
{
    public function __construct(
        private MessageBusInterface $commandBus
    ) {
    }

    public function retract(array $userIds, array $tags): void
    {
        $userIds = array_values(array_unique(array_map(static fn (Uuid $userId): string => $userId->value(), $userIds)));
        $tags = array_values(array_unique(array_filter($tags, static fn (mixed $tag): bool => is_string($tag) && $tag !== '')));

        if ($userIds === [] || $tags === []) {
            return;
        }

        $this->commandBus->dispatch(new RetractPushCommand($userIds, $tags));
    }
}
