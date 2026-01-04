<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterUserRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required')]
        #[Assert\Length(
            min: 1,
            max: 255,
            minMessage: 'Name must be at least {{ limit }} character long',
            maxMessage: 'Name cannot be longer than {{ limit }} characters'
        )]
        public string $name,

        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'The email "{{ value }}" is not a valid email address')]
        public string $email,

        #[Assert\NotBlank(message: 'Password is required')]
        #[Assert\Length(
            min: 8,
            minMessage: 'Password must be at least {{ limit }} characters long'
        )]
        public string $password,

        #[Assert\Length(
            max: 20,
            maxMessage: 'Phone number cannot be longer than {{ limit }} characters'
        )]
        public ?string $phoneNumber = null
    ) {
    }
}
