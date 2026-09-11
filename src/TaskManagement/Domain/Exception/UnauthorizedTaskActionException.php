<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Exception;

use DomainException;

class UnauthorizedTaskActionException extends DomainException
{
    public function __construct(string $message = "You are not authorized to perform this action")
    {
        parent::__construct($message);
    }
}
