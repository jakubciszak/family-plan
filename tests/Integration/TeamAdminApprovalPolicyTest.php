<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Policy\TeamAdminApprovalPolicy;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Role;

class TeamAdminApprovalPolicyTest extends IntegrationTestCase
{
    private TeamAdminApprovalPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TeamAdminApprovalPolicy(
            $this->service(UserRepositoryInterface::class),
            $this->service(TeamMembershipRepositoryInterface::class)
        );
    }

    public function testTeamAdminCanApproveTasksInThatTeam(): void
    {
        $founder = $this->user('Rodzic');
        $teamId = $this->team($founder);

        $this->assertTrue($this->policy->canApprove($founder->id(), $teamId));
    }

    public function testPlainMemberCannotApprove(): void
    {
        $founder = $this->user('Rodzic');
        $teamId = $this->team($founder);
        $child = $this->user('Dziecko');
        $this->join($teamId, $child, TeamRole::member());

        $this->assertFalse($this->policy->canApprove($child->id(), $teamId));
    }

    public function testGlobalAdminCanApproveWithoutTeamContext(): void
    {
        $superAdmin = $this->user('Administrator', Role::ADMIN);

        $this->assertTrue($this->policy->canApprove($superAdmin->id()));
    }

    public function testMemberCannotApproveWhenTeamIsUnknown(): void
    {
        $founder = $this->user('Rodzic');
        $this->team($founder);

        $this->assertFalse($this->policy->canApprove($founder->id()));
    }

    public function testAdminOfOneTeamCannotApproveInAnother(): void
    {
        $founder = $this->user('Rodzic');
        $this->team($founder);
        $stranger = $this->user('Obcy');
        $otherTeamId = $this->team($stranger, 'Obca rodzina');

        $this->assertFalse($this->policy->canApprove($founder->id(), $otherTeamId));
    }

    public function testNobodyApprovesForAnAccountThatDoesNotExist(): void
    {
        $this->assertFalse($this->policy->canApprove(Uuid::generate()));
    }
}
