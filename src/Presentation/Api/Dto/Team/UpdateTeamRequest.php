<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Team;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateTeamRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Team name is required')]
        #[Assert\Length(
            min: 1,
            max: 255,
            minMessage: 'Team name must be at least {{ limit }} characters long',
            maxMessage: 'Team name cannot be longer than {{ limit }} characters'
        )]
        public string $name,

        public ?string $description = null
    ) {
    }
}
