<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Command;

final class RemoveMemberCommand
{
    public function __construct(
        public readonly string $teamId,
        public readonly string $userId,
        public readonly string $removedBy
    ) {
    }
}
