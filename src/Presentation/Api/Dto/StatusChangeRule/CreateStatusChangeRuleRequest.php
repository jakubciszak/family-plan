<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\StatusChangeRule;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateStatusChangeRuleRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $taskTemplateId,

        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 255)]
        public string $name,

        #[Assert\NotBlank]
        public string $description,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['other_task_completed_today', 'last_execution_cooldown'])]
        public string $conditionType,

        #[Assert\NotBlank]
        #[Assert\Type('array')]
        public array $conditionConfig
    ) {
    }
}
