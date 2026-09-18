<?php

declare(strict_types=1);

namespace App\ActionPlanning\Application\Command;

use App\Shared\Domain\ValueObject\Uuid;

final readonly class SaveActionPlanCommand
{
    public function __construct(
        public Uuid $userId,
        public ?Uuid $id,
        public string $name,
        public array $steps,
        public ?int $estimatedMinutes,
        public int $reminderMinutes,
        public ?Uuid $teamId = null,
        public ?string $reminderSound = null,
    ) {
    }
}
