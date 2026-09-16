<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Push;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SendPushAnnouncementRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Message is required', normalizer: 'trim')]
        #[Assert\Length(max: 500)]
        public string $message,

        #[Assert\Length(max: 80)]
        public ?string $title = null,

        #[Assert\Uuid(message: 'Recipient must be a user id')]
        public ?string $userId = null
    ) {
    }
}
