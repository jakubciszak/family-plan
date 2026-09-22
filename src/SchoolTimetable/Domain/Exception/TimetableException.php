<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Domain\Exception;

final class TimetableException extends \DomainException
{
    public function __construct(public readonly string $errorCode, public readonly int $status = 422, public readonly array $details = [], ?string $message = null)
    {
        parent::__construct($message ?? $errorCode);
    }

    public static function invalid(string $message): self
    {
        return new self('validation_failed', 422, [], $message);
    }

    public static function notConfigured(): self
    {
        return new self('school_account_missing', 404);
    }

    public static function denied(): self
    {
        return new self('access_denied', 403);
    }

    public static function signInRejected(): self
    {
        return new self('mobidziennik_sign_in_rejected', 422);
    }

    public static function unreachable(string $message): self
    {
        return new self('mobidziennik_unreachable', 502, [], $message);
    }
}
