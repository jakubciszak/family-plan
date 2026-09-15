<?php

declare(strict_types=1);

namespace App\Allowance\Infrastructure\Persistence;

use App\Allowance\Domain\Repository\WeekClosureRepositoryInterface;
use App\Allowance\Domain\ValueObject\WeekStart;
use App\Shared\Domain\Period\ClosedWeeksInterface;
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final readonly class SettledWeeks implements ClosedWeeksInterface
{
    public function __construct(private WeekClosureRepositoryInterface $closures)
    {
    }

    public function isClosedFor(Uuid $userId, DateTimeImmutable $day): bool
    {
        return $this->closures->find($userId, WeekStart::of($day)) !== null;
    }
}
