<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Uuid;
use DomainException;

/**
 * What sits behind the app: nothing, one of the drawn patterns, or a picture the owner uploaded.
 */
final readonly class Backdrop
{
    public const PATTERNS = ['plain', 'dots', 'waves', 'grid', 'stars', 'bubbles', 'confetti'];

    private function __construct(
        private string $pattern,
        private ?string $imageId,
        private int $dimming
    ) {
    }

    public static function none(): self
    {
        return new self('plain', null, 0);
    }

    public static function drawn(string $pattern): self
    {
        if (!in_array($pattern, self::PATTERNS, true)) {
            throw new DomainException('That backdrop is not one we draw');
        }

        return new self($pattern, null, 0);
    }

    public static function picture(Uuid $imageId, int $dimming): self
    {
        if ($dimming < 0 || $dimming > 90) {
            throw new DomainException('Dimming runs from nothing to ninety percent');
        }

        return new self('plain', $imageId->value(), $dimming);
    }

    public static function fromArray(array $held): self
    {
        $imageId = $held['imageId'] ?? null;

        if (is_string($imageId) && $imageId !== '') {
            return self::picture(Uuid::fromString($imageId), (int) ($held['dimming'] ?? 40));
        }

        return self::drawn($held['pattern'] ?? 'plain');
    }

    public function isPicture(): bool
    {
        return $this->imageId !== null;
    }

    public function pattern(): string
    {
        return $this->pattern;
    }

    public function imageId(): ?Uuid
    {
        return $this->imageId === null ? null : Uuid::fromString($this->imageId);
    }

    public function dimming(): int
    {
        return $this->dimming;
    }

    public function toArray(): array
    {
        return [
            'pattern' => $this->pattern,
            'imageId' => $this->imageId,
            'dimming' => $this->dimming,
        ];
    }
}
