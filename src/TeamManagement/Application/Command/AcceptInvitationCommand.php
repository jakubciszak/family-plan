<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Command;

final class AcceptInvitationCommand
{
    public function __construct(
        public readonly string $token,
        public readonly string $userId
    ) {
    }
}
