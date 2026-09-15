<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Push;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterPushSubscriptionRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Endpoint is required')]
        #[Assert\Url(message: 'Endpoint must be a valid URL', requireTld: true)]
        #[Assert\Length(max: 500)]
        public string $endpoint,

        #[Assert\NotBlank(message: 'Subscription public key is required')]
        #[Assert\Length(max: 255)]
        public string $publicKey,

        #[Assert\NotBlank(message: 'Subscription auth token is required')]
        #[Assert\Length(max: 255)]
        public string $authToken,

        #[Assert\Length(max: 255)]
        public ?string $deviceLabel = null
    ) {
    }
}
