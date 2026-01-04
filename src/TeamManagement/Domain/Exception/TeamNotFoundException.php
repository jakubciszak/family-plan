<?php

declare(strict_types=1);

namespace App\TeamManagement\Domain\Exception;

use DomainException;

class TeamNotFoundException extends DomainException
{
    public function __construct(string $teamId)
    {
        parent::__construct("Team with id {$teamId} not found");
    }
}
