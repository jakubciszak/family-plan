<?php

declare(strict_types=1);

namespace App\TeamManagement\Application\Query;

final class GetUserInvitationsQuery
{
    public function __construct(
        public readonly string $email
    ) {
    }
}
