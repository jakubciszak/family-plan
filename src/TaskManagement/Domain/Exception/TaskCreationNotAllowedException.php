<?php

declare(strict_types=1);

namespace App\TaskManagement\Domain\Exception;

use DomainException;

final class TaskCreationNotAllowedException extends DomainException
{
    public static function notTeamAdmin(): self
    {
        return new self('Only team administrators can create tasks');
    }

    public static function teamRequired(): self
    {
        return new self('Task must belong to a team');
    }
}
