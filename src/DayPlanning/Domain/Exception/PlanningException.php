<?php

declare(strict_types=1);

namespace App\DayPlanning\Domain\Exception;

final class PlanningException extends \DomainException
{
    public function __construct(public readonly string $errorCode, public readonly int $status = 422, public readonly array $details = [], ?string $message = null)
    {
        parent::__construct($message ?? $errorCode);
    }

    public static function invalid(string $message): self
    {
        return new self('validation_failed', 422, [], $message);
    }

    public static function notFound(): self
    {
        return new self('not_found', 404);
    }
}
