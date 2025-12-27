<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\BonusRule;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateBonusRuleRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required')]
        #[Assert\Length(
            min: 1,
            max: 255,
            minMessage: 'Name must be at least {{ limit }} character long',
            maxMessage: 'Name cannot be longer than {{ limit }} characters'
        )]
        public string $name,

        #[Assert\NotBlank(message: 'Description is required')]
        #[Assert\Length(
            min: 1,
            max: 1000,
            minMessage: 'Description must be at least {{ limit }} character long',
            maxMessage: 'Description cannot be longer than {{ limit }} characters'
        )]
        public string $description,

        #[Assert\NotNull(message: 'Bonus points are required')]
        #[Assert\Type(type: 'integer', message: 'Bonus points must be an integer')]
        #[Assert\Range(
            min: 1,
            max: 1000,
            notInRangeMessage: 'Bonus points must be between {{ min }} and {{ max }}'
        )]
        public int $bonusPoints,

        #[Assert\NotBlank(message: 'Rule type is required')]
        #[Assert\Choice(
            choices: ['consecutive_days', 'monthly_task_count'],
            message: 'Rule type must be either "consecutive_days" or "monthly_task_count"'
        )]
        public string $ruleType,

        #[Assert\NotNull(message: 'Rule config is required')]
        #[Assert\Type(type: 'array', message: 'Rule config must be an object')]
        public array $ruleConfig
    ) {
    }
}
