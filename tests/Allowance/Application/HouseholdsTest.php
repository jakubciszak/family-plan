<?php

declare(strict_types=1);

namespace App\Tests\Allowance\Application;

use App\Allowance\Application\Service\Households;
use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HouseholdsTest extends TestCase
{
    public function testAdminAndMemberResolveTheSameLatestFamilyRegardlessOfRepositoryOrder(): void
    {
        $member = Uuid::generate();
        $admin = Uuid::generate();
        $private = new TeamMembership(Uuid::generate(), Uuid::generate(), $member, TeamRole::admin(), new DateTimeImmutable('2026-09-18'));
        $older = new TeamMembership(Uuid::generate(), Uuid::generate(), $member, TeamRole::member(), new DateTimeImmutable('2026-09-10'));
        $newer = new TeamMembership(Uuid::generate(), Uuid::generate(), $member, TeamRole::member(), new DateTimeImmutable('2026-09-15'));
        $repository = $this->createStub(TeamMembershipRepositoryInterface::class);
        $repository->method('isAdmin')->willReturnCallback(static fn (Uuid $user, Uuid $team) => !$team->equals($private->teamId()));
        $repository->method('ofUser')->willReturnOnConsecutiveCalls(
            [$private, $older, $newer],
            [$private, $older, $newer],
            [$newer, $older, $private],
            [$newer, $older, $private]
        );
        $households = new Households($repository);

        foreach (range(1, 2) as $ignored) {
            $this->assertTrue($newer->teamId()->equals($households->teamOf($member)));
            $this->assertTrue($newer->teamId()->equals($households->sharedWithAdmin($admin, $member)));
        }
    }
}
