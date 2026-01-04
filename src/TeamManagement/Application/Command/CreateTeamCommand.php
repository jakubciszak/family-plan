<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Command;

final class CreateTeamCommand
{
    public function __construct(
        public readonly string $teamId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $createdBy
    ) {
    }
}
