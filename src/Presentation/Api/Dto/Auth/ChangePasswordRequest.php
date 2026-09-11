<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangePasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Current password is required')]
        public string $currentPassword,

        #[Assert\NotBlank(message: 'New password is required')]
        #[Assert\Length(
            min: 8,
            minMessage: 'Password must be at least {{ limit }} characters long'
        )]
        public string $newPassword
    ) {
    }
}
