<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Allowance;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetAllowanceRuleRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $teamId,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['tasks', 'bonuses'], message: 'Unknown points account')]
        public string $pointsAccount,

        #[Assert\NotNull]
        #[Assert\Range(min: 0, max: 100000)]
        public int $minimumPoints,

        #[Assert\NotNull]
        #[Assert\Range(min: 0, max: 100000000)]
        public int $rateAmount,

        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 100000)]
        public int $ratePerPoints
    ) {
    }
}
