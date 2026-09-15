<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Personalisation;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PictureRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['avatar', 'backdrop'])]
        public string $purpose,

        #[Assert\NotBlank]
        #[Assert\Length(max: 4000000)]
        public string $data
    ) {
    }
}
