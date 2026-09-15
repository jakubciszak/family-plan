<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Allowance;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CloseWeekRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $userId,

        #[Assert\NotBlank]
        #[Assert\Date]
        public string $weekStart
    ) {
    }
}
