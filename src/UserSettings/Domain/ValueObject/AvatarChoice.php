<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;
use DomainException;

/**
 * Either one of the drawn avatars, picked by style and seed, or a picture the owner uploaded.
 */
final readonly class AvatarChoice
{
    public const STYLES = [
        'adventurer', 'avataaars', 'big-smile', 'bottts', 'croodles',
        'fun-emoji', 'lorelei', 'micah', 'miniavs', 'notionists',
        'open-peeps', 'personas', 'pixel-art', 'thumbs',
    ];

    private function __construct(
        private string $style,
        private string $seed,
        private ?string $imageId
    ) {
    }

    public static function drawn(string $style, string $seed): self
    {
        if (!in_array($style, self::STYLES, true)) {
            throw new DomainException('That avatar style is not one we draw');
        }

        $seed = trim($seed);

        if ($seed === '' || mb_strlen($seed) > 60) {
            throw new DomainException('An avatar seed is between one and sixty characters');
        }

        return new self($style, $seed, null);
    }

    public static function uploaded(Uuid $imageId): self
    {
        return new self(self::STYLES[0], '', $imageId->value());
    }

    public static function fromArray(array $held): self
    {
        $imageId = $held['imageId'] ?? null;

        if (is_string($imageId) && $imageId !== '') {
            return self::uploaded(Uuid::fromString($imageId));
        }

        return self::drawn($held['style'] ?? self::STYLES[0], $held['seed'] ?? 'kot');
    }

    public function isUploaded(): bool
    {
        return $this->imageId !== null;
    }

    public function style(): string
    {
        return $this->style;
    }

    public function seed(): string
    {
        return $this->seed;
    }

    public function imageId(): ?Uuid
    {
        return $this->imageId === null ? null : Uuid::fromString($this->imageId);
    }

    public function toArray(): array
    {
        return [
            'style' => $this->style,
            'seed' => $this->seed,
            'imageId' => $this->imageId,
        ];
    }
}
