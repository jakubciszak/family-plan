<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\BonusRule;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateBonusRuleRequest
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
        public int $bonusPoints
    ) {
    }
}
