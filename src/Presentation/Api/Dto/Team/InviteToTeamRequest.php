<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Team;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class InviteToTeamRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'The email "{{ value }}" is not a valid email address')]
        public string $email,

        #[Assert\NotBlank(message: 'Role is required')]
        #[Assert\Choice(
            choices: ['admin', 'member'],
            message: 'Role must be either "admin" or "member"'
        )]
        public string $role
    ) {
    }
}
