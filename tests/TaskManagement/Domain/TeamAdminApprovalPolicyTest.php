<?php

declare(strict_types=1);

namespace App\Tests\TaskManagement\Domain;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Policy\TeamAdminApprovalPolicy;
use App\TeamManagement\Domain\Repository\TeamMemberRepositoryInterface;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use PHPUnit\Framework\TestCase;

class TeamAdminApprovalPolicyTest extends TestCase
{
    public function testTeamAdminCanApproveTasksInThatTeam(): void
    {
        $userId = Uuid::generate();
        $teamId = Uuid::generate();

        $policy = $this->policyFor($userId, Role::USER, $teamId, true);

        $this->assertTrue($policy->canApprove($userId, $teamId));
    }

    public function testPlainMemberCannotApprove(): void
    {
        $userId = Uuid::generate();
        $teamId = Uuid::generate();

        $policy = $this->policyFor($userId, Role::USER, $teamId, false);

        $this->assertFalse($policy->canApprove($userId, $teamId));
    }

    public function testGlobalAdminCanApproveWithoutTeamContext(): void
    {
        $userId = Uuid::generate();

        $policy = $this->policyFor($userId, Role::ADMIN, null, false);

        $this->assertTrue($policy->canApprove($userId));
    }

    public function testMemberCannotApproveWhenTeamIsUnknown(): void
    {
        $userId = Uuid::generate();

        $policy = $this->policyFor($userId, Role::USER, null, true);

        $this->assertFalse($policy->canApprove($userId));
    }

    private function policyFor(Uuid $userId, Role $role, ?Uuid $teamId, bool $isTeamAdmin): TeamAdminApprovalPolicy
    {
        $user = User::create(
            $userId,
            'Rodzic',
            Email::fromString('rodzic@example.com'),
            password_hash('password123', PASSWORD_BCRYPT),
            $role
        );

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn($user);

        $memberRepository = $this->createMock(TeamMemberRepositoryInterface::class);
        $memberRepository->method('isUserAdminOfTeam')->willReturn($isTeamAdmin);

        return new TeamAdminApprovalPolicy($userRepository, $memberRepository);
    }
}
