<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\TaskType;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateTaskTypeRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Team is required')]
        #[Assert\Uuid(message: 'Team id must be a valid UUID')]
        public string $teamId,

        #[Assert\NotBlank(message: 'Name is required')]
        #[Assert\Length(min: 1, max: 255)]
        public string $name,

        #[Assert\NotNull(message: 'Points are required')]
        #[Assert\Type(type: 'integer')]
        #[Assert\Range(min: 0, max: 1000)]
        public int $points,

        #[Assert\NotBlank(message: 'Frequency is required')]
        #[Assert\Choice(choices: ['once', 'daily', 'weekly', 'monthly'])]
        public string $frequency,

        #[Assert\Length(max: 1000)]
        public string $description = '',

        #[Assert\Type(type: 'array')]
        public array $executionLimit = ['type' => 'unlimited'],

        #[Assert\Uuid(versions: [4])]
        public ?string $actionPlanId = null
    ) {
    }
}
