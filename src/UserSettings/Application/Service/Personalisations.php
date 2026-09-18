<?php

declare(strict_types=1);

namespace App\UserSettings\Application\Service;

use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\UserSettings\Domain\Entity\Personalisation;
use App\UserSettings\Domain\Repository\PersonalisationRepositoryInterface;

final readonly class Personalisations
{
    public function __construct(
        private PersonalisationRepositoryInterface $repository,
        private ClockInterface $clock
    ) {
    }

    public function of(Uuid $userId): Personalisation
    {
        $held = $this->repository->ofUser($userId);

        if ($held === null) {
            $held = Personalisation::asItComes(Uuid::generate(), $userId, $this->clock);
            $this->repository->save($held);
        }

        return $held;
    }

    public function save(Personalisation $personalisation): void
    {
        $this->repository->save($personalisation);
    }

    /**
     * @param Uuid[] $userIds
     * @return array<string, Personalisation>
     */
    public function forMany(array $userIds): array
    {
        $found = [];

        foreach ($this->repository->ofUsers($userIds) as $personalisation) {
            $found[$personalisation->userId()->value()] = $personalisation;
        }

        return $found;
    }

    /**
     * What to call someone: the name they chose for themselves, or the one on the account.
     *
     * @param Uuid[] $userIds
     * @return array<string, string> nickname per user id, users without one left out
     */
    public function nicknames(array $userIds): array
    {
        $names = [];

        foreach ($this->forMany($userIds) as $id => $personalisation) {
            if ($personalisation->nickname() !== null) {
                $names[$id] = $personalisation->nickname();
            }
        }

        return $names;
    }

    public function describe(Personalisation $personalisation): array
    {
        $avatar = $personalisation->avatar();
        $backdrop = $personalisation->backdrop();

        return [
            'userId' => $personalisation->userId()->value(),
            'nickname' => $personalisation->nickname(),
            'theme' => $personalisation->theme(),
            'themeMode' => $personalisation->themeMode(),
            'language' => $personalisation->language(),
            'avatar' => [
                'style' => $avatar->style(),
                'seed' => $avatar->seed(),
                'pictureId' => $avatar->imageId()?->value(),
            ],
            'backdrop' => [
                'pattern' => $backdrop->pattern(),
                'pictureId' => $backdrop->imageId()?->value(),
                'dimming' => $backdrop->dimming(),
            ],
            'home' => $personalisation->home()->order(),
            'navigation' => $personalisation->navigation()->order(),
            'celebrates' => $personalisation->celebrates(),
            'makesSound' => $personalisation->makesSound(),
            'places' => [
                'home' => Personalisation::HOME_PLACES,
                'navigation' => Personalisation::NAV_PLACES,
            ],
            'choices' => [
                'themeModes' => Personalisation::THEME_MODES,
                'languages' => Personalisation::LANGUAGES,
            ],
        ];
    }

    public function face(Personalisation $personalisation): array
    {
        $avatar = $personalisation->avatar();

        return [
            'nickname' => $personalisation->nickname(),
            'theme' => $personalisation->theme(),
            'avatar' => [
                'style' => $avatar->style(),
                'seed' => $avatar->seed(),
                'pictureId' => $avatar->imageId()?->value(),
            ],
        ];
    }
}
