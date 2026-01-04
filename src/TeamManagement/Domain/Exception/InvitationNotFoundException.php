<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Exception;

use DomainException;

class InvitationNotFoundException extends DomainException
{
    public function __construct(string $invitationId)
    {
        parent::__construct("Invitation with id {$invitationId} not found");
    }
}
