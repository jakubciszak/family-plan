<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\ActionPlanning;

use App\ActionPlanning\Domain\Entity\ActionPlan;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ActionPlanRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public string $name = '',
        #[Assert\Count(min: 1, max: 100)]
        public array $steps = [],
        #[Assert\Range(min: 1, max: 1440)]
        public ?int $estimatedMinutes = null,
        #[Assert\Range(min: 1, max: 120)]
        public int $reminderMinutes = 10,
        #[Assert\Uuid(versions: [4])]
        public ?string $teamId = null,
        #[Assert\Choice(choices: ActionPlan::REMINDER_SOUNDS)]
        public ?string $reminderSound = null,
    ) {
    }
}
