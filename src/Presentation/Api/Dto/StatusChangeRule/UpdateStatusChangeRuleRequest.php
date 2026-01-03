<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\StatusChangeRule;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateStatusChangeRuleRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 255)]
        public string $name,

        #[Assert\NotBlank]
        public string $description
    ) {
    }
}
