<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Allowance;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GoalRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public string $name,

        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 100000000)]
        public int $target,

        #[Assert\Date]
        public ?string $wantedBy = null
    ) {
    }
}
