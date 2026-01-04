<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Exception;

use DomainException;

class UnauthorizedTeamActionException extends DomainException
{
    public function __construct(string $message = "You are not authorized to perform this action")
    {
        parent::__construct($message);
    }
}
