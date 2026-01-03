<?php

declare(strict_types=1);

namespace App\Notifications\Domain\ValueObject;

final readonly class NotificationMessage
{
    private function __construct(
        private string $content,
        private ?string $subject,
        private array $additionalParameters
    ) {
    }

    public static function create(
        string $content,
        ?string $subject = null,
        array $additionalParameters = []
    ): self {
        if (empty($content)) {
            throw new \InvalidArgumentException('Message content cannot be empty');
        }

        if ($subject !== null && empty($subject)) {
            throw new \InvalidArgumentException('Message subject cannot be empty');
        }

        return new self($content, $subject, $additionalParameters);
    }

    public function content(): string
    {
        return $this->content;
    }

    public function subject(): ?string
    {
        return $this->subject;
    }

    public function additionalParameters(): array
    {
        return $this->additionalParameters;
    }

    public function getParameter(string $key): mixed
    {
        return $this->additionalParameters[$key] ?? null;
    }

    public function hasParameter(string $key): bool
    {
        return array_key_exists($key, $this->additionalParameters);
    }
}
