<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\Entity;

use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\ValueObject\AvatarChoice;
use App\UserSettings\Domain\ValueObject\Backdrop;
use App\UserSettings\Domain\ValueObject\Layout;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

/**
 * How one person wants the app to look and what they want to see on it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'personalisations')]
#[ORM\Index(columns: ['user_id'])]
class Personalisation
{
    public const HOME_PLACES = ['week', 'tasks', 'standings', 'members'];

    public const HOME_START = ['week', 'tasks', 'standings'];

    public const NAV_PLACES = ['tasks', 'day-planning', 'action-plans', 'teams', 'allowance', 'personalise', 'task-types', 'bonus-rules', 'account', 'settings'];

    public const DEFAULT_THEME = '#2e7d5b';

    public const THEME_MODES = ['light', 'dark', 'system'];

    public const DEFAULT_THEME_MODE = 'system';

    public const LANGUAGES = ['pl', 'en'];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid', unique: true)]
        private Uuid $userId,

        #[ORM\Column(type: 'string', length: 40, nullable: true)]
        private ?string $nickname,

        #[ORM\Column(type: 'string', length: 7)]
        private string $theme,

        #[ORM\Column(type: 'string', length: 10)]
        private string $themeMode,

        #[ORM\Column(type: 'string', length: 5, nullable: true)]
        private ?string $language,

        #[ORM\Column(type: 'json')]
        private array $avatar,

        #[ORM\Column(type: 'json')]
        private array $backdrop,

        #[ORM\Column(type: 'json')]
        private array $home,

        #[ORM\Column(type: 'json')]
        private array $navigation,

        #[ORM\Column(type: 'boolean')]
        private bool $celebrates,

        #[ORM\Column(type: 'boolean')]
        private bool $makesSound,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt,

        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?DateTimeImmutable $updatedAt = null
    ) {
    }

    public static function asItComes(Uuid $id, Uuid $userId, ClockInterface $clock): self
    {
        return new self(
            $id,
            $userId,
            null,
            self::DEFAULT_THEME,
            self::DEFAULT_THEME_MODE,
            null,
            AvatarChoice::drawn('bottts', $userId->value())->toArray(),
            Backdrop::none()->toArray(),
            self::HOME_START,
            self::NAV_PLACES,
            true,
            true,
            $clock->now()
        );
    }

    public function callThemselves(?string $nickname, ClockInterface $clock): void
    {
        $nickname = $nickname === null ? null : trim($nickname);

        if ($nickname === '') {
            $nickname = null;
        }

        if ($nickname !== null && mb_strlen($nickname) > 40) {
            throw new DomainException('A nickname is at most forty characters');
        }

        $this->nickname = $nickname;
        $this->touch($clock);
    }

    public function paintWith(string $theme, ClockInterface $clock): void
    {
        if (!preg_match('/^#[0-9a-f]{6}$/i', $theme)) {
            throw new DomainException('A colour is given as #rrggbb');
        }

        $this->theme = strtolower($theme);
        $this->touch($clock);
    }

    public function lightOrDark(string $themeMode, ClockInterface $clock): void
    {
        if (!in_array($themeMode, self::THEME_MODES, true)) {
            throw new DomainException('A theme mode is light, dark or system');
        }

        $this->themeMode = $themeMode;
        $this->touch($clock);
    }

    public function speak(string $language, ClockInterface $clock): void
    {
        if (!in_array($language, self::LANGUAGES, true)) {
            throw new DomainException('A language is one of those the app speaks');
        }

        $this->language = $language;
        $this->touch($clock);
    }

    public function wear(AvatarChoice $avatar, ClockInterface $clock): void
    {
        $this->avatar = $avatar->toArray();
        $this->touch($clock);
    }

    public function standAgainst(Backdrop $backdrop, ClockInterface $clock): void
    {
        $this->backdrop = $backdrop->toArray();
        $this->touch($clock);
    }

    public function arrangeHome(Layout $layout, ClockInterface $clock): void
    {
        $this->home = $layout->order();
        $this->touch($clock);
    }

    public function arrangeNavigation(Layout $layout, ClockInterface $clock): void
    {
        $this->navigation = $layout->order();
        $this->touch($clock);
    }

    public function celebrate(bool $celebrates, bool $makesSound, ClockInterface $clock): void
    {
        $this->celebrates = $celebrates;
        $this->makesSound = $makesSound;
        $this->touch($clock);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function nickname(): ?string
    {
        return $this->nickname;
    }

    public function theme(): string
    {
        return $this->theme;
    }

    public function themeMode(): string
    {
        return $this->themeMode;
    }

    public function language(): ?string
    {
        return $this->language;
    }

    public function avatar(): AvatarChoice
    {
        return AvatarChoice::fromArray($this->avatar);
    }

    public function backdrop(): Backdrop
    {
        return Backdrop::fromArray($this->backdrop);
    }

    public function home(): Layout
    {
        return Layout::of(self::HOME_PLACES, $this->home);
    }

    public function navigation(): Layout
    {
        return Layout::of(self::NAV_PLACES, $this->navigation);
    }

    public function celebrates(): bool
    {
        return $this->celebrates;
    }

    public function makesSound(): bool
    {
        return $this->makesSound;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(ClockInterface $clock): void
    {
        $this->updatedAt = $clock->now();
    }
}
