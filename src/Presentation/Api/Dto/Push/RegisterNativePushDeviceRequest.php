<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Push;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterNativePushDeviceRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Device token is required', normalizer: 'trim')]
        #[Assert\Length(max: 512)]
        public string $token,

        #[Assert\Choice(choices: ['android'], message: 'Unsupported platform')]
        public string $platform = 'android',

        #[Assert\Length(max: 255)]
        public ?string $deviceLabel = null
    ) {
    }
}
