<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Allowance;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class OfferPayoutRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $userId,

        #[Assert\NotNull]
        #[Assert\Range(min: 1, max: 100000000)]
        public int $amount,

        #[Assert\Length(max: 255)]
        public ?string $note = null
    ) {
    }
}
