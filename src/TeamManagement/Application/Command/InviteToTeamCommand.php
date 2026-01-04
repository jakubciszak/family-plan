<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Command;

final class InviteToTeamCommand
{
    public function __construct(
        public readonly string $invitationId,
        public readonly string $teamId,
        public readonly string $email,
        public readonly string $role,
        public readonly string $invitedBy
    ) {
    }
}
