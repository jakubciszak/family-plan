<?php

declare(strict_types=1);

namespace App\Notifications\Application\Port;

use App\Shared\Domain\ValueObject\Uuid;

/**
 * Who caused what is being notified about, so nobody gets told about their own action.
 */
interface ActorProviderInterface
{
    /**
     * The signed-in user behind the current request; null in the console, the worker and tests.
     */
    public function currentActorId(): ?Uuid;
}
