<?php

declare(strict_types=1);

namespace App\UserSettings\Domain\Entity;

use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

/**
 * A picture somebody uploaded for their own avatar or backdrop.
 */
#[ORM\Entity]
#[ORM\Table(name: 'own_pictures')]
#[ORM\Index(columns: ['user_id', 'purpose'])]
class OwnPicture
{
    public const PURPOSES = ['avatar', 'backdrop'];

    private const LARGEST = [
        'avatar' => 512 * 1024,
        'backdrop' => 2 * 1024 * 1024,
    ];

    private const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'uuid')]
        private Uuid $id,

        #[ORM\Column(type: 'uuid')]
        private Uuid $userId,

        #[ORM\Column(type: 'string', length: 20)]
        private string $purpose,

        #[ORM\Column(type: 'string', length: 30)]
        private string $mimeType,

        #[ORM\Column(type: 'integer')]
        private int $width,

        #[ORM\Column(type: 'integer')]
        private int $height,

        #[ORM\Column(type: 'integer')]
        private int $byteSize,

        #[ORM\Column(type: 'text')]
        private string $content,

        #[ORM\Column(type: 'datetime_immutable')]
        private DateTimeImmutable $createdAt
    ) {
    }

    public static function keep(
        Uuid $id,
        Uuid $userId,
        string $purpose,
        string $bytes,
        ClockInterface $clock
    ): self {
        if (!in_array($purpose, self::PURPOSES, true)) {
            throw new DomainException('A picture is kept as an avatar or as a backdrop');
        }

        if (strlen($bytes) > self::LARGEST[$purpose]) {
            throw new DomainException('That picture is too heavy');
        }

        $measured = @getimagesizefromstring($bytes);

        if ($measured === false || !in_array($measured['mime'] ?? '', self::TYPES, true)) {
            throw new DomainException('That file is not a picture we can show');
        }

        return new self(
            $id,
            $userId,
            $purpose,
            $measured['mime'],
            (int) $measured[0],
            (int) $measured[1],
            strlen($bytes),
            base64_encode($bytes),
            $clock->now()
        );
    }

    public function belongsTo(Uuid $userId): bool
    {
        return $this->userId->equals($userId);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function userId(): Uuid
    {
        return $this->userId;
    }

    public function purpose(): string
    {
        return $this->purpose;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function byteSize(): int
    {
        return $this->byteSize;
    }

    public function bytes(): string
    {
        return base64_decode($this->content, true) ?: '';
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
