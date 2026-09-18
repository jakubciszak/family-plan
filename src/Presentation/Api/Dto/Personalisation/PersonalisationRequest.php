<?php

declare(strict_types=1);

namespace App\Presentation\Api\Dto\Personalisation;

use App\UserSettings\Domain\Entity\Personalisation;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class PersonalisationRequest
{
    public function __construct(
        #[Assert\Length(max: 40)]
        public ?string $nickname = null,

        #[Assert\Regex(pattern: '/^#[0-9a-fA-F]{6}$/', message: 'A colour is given as #rrggbb')]
        public ?string $theme = null,

        #[Assert\Choice(choices: Personalisation::THEME_MODES, message: 'A theme mode is light, dark or system')]
        public ?string $themeMode = null,

        #[Assert\Choice(choices: Personalisation::LANGUAGES, message: 'A language is one of those the app speaks')]
        public ?string $language = null,

        #[Assert\Type('array')]
        public ?array $avatar = null,

        #[Assert\Type('array')]
        public ?array $backdrop = null,

        #[Assert\Type('array')]
        public ?array $home = null,

        #[Assert\Type('array')]
        public ?array $navigation = null,

        public ?bool $celebrates = null,

        public ?bool $makesSound = null
    ) {
    }
}
