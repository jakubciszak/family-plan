<?php

declare(strict_types=1);

namespace App\ActionPlanning\Application\Command;

use App\Shared\Domain\ValueObject\Uuid;

final readonly class DeleteActionPlanCommand
{
    public function __construct(public Uuid $userId, public Uuid $id)
    {
    }
}
