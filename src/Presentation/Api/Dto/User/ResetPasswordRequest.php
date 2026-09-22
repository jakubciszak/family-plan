<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\User;

use App\UserManagement\Application\Command\ResetUserPasswordCommand;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ResetPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'New password is required')]
        #[Assert\Length(
            min: ResetUserPasswordCommand::MIN_PASSWORD_LENGTH,
            minMessage: 'Password must be at least {{ limit }} characters long'
        )]
        public string $newPassword
    ) {
    }
}
