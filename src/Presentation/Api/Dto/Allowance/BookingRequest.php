<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Allowance;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class BookingRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 100000000)]
        public int $amount,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $description,

        #[Assert\Date]
        public ?string $on = null
    ) {
    }
}
