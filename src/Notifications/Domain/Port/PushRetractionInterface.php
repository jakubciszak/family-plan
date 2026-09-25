<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Port;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * Takes notifications that stopped being true off the phones they reached. A tray keeps a push until
 * somebody swipes it away, also after the other parent has approved the task it asks about.
 */
interface PushRetractionInterface
{
    /**
     * @param list<Uuid>   $userIds whose phones may still show them
     * @param list<string> $tags    the tags the pushes went out with
     */
    public function retract(array $userIds, array $tags): void;
}
