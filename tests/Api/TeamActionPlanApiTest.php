<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\ValueObject\Role;

final class TeamActionPlanApiTest extends ApiTestCase
{
    private function team(): string
    {
        return $this->assertJsonResponse($this->postJson('/api/teams', ['name' => 'Family', 'description' => null]), 201)['id'];
    }

    private function member(string $teamId): User
    {
        $member = $this->authenticate(Role::USER);
        static::getContainer()->get(TeamMembershipRepositoryInterface::class)->join(Uuid::fromString($teamId), $member->id(), TeamRole::member());

        return $member;
    }

    private function plan(?string $teamId): array
    {
        return ['name' => 'Room', 'teamId' => $teamId, 'steps' => [['name' => 'Bed', 'stages' => []]], 'estimatedMinutes' => 5, 'reminderMinutes' => 10, 'reminderSound' => 'bell'];
    }

    private function taskType(string $teamId, ?string $planId): array
    {
        return ['teamId' => $teamId, 'name' => 'Clean room', 'points' => 10, 'frequency' => 'daily', 'actionPlanId' => $planId];
    }

    public function testAnyMemberCanCreateTeamPlansButOnlyAuthorAndTeamAdminCanManageThem(): void
    {
        $admin = $this->currentUser;
        $team = $this->team();
        $author = $this->member($team);
        $this->loginAs($author);
        $shared = $this->assertJsonResponse($this->postJson('/api/action-plans', $this->plan($team)), 201);
        $private = $this->assertJsonResponse($this->postJson('/api/action-plans', $this->plan(null)), 201);
        $other = $this->member($team);
        $this->loginAs($other);
        $visible = $this->getJson('/api/action-plans')['plans'];
        self::assertSame([$shared['id']], array_column($visible, 'id'));
        self::assertFalse($visible[0]['canManage']);
        self::assertSame(404, $this->putJson('/api/action-plans/'.$shared['id'], $this->plan($team))->getStatusCode());
        self::assertSame(404, $this->deleteJson('/api/action-plans/'.$shared['id'])->getStatusCode());
        $this->loginAs($admin);
        self::assertTrue($this->getJson('/api/action-plans')['plans'][0]['canManage']);
        self::assertSame(404, $this->putJson('/api/action-plans/'.$private['id'], $this->plan(null))->getStatusCode());
        self::assertSame(400, $this->putJson('/api/action-plans/'.$shared['id'], $this->plan(null))->getStatusCode());
        self::assertSame(200, $this->putJson('/api/action-plans/'.$shared['id'], $this->plan($team))->getStatusCode());
        self::assertSame(204, $this->deleteJson('/api/action-plans/'.$shared['id'])->getStatusCode());
    }

    public function testNonMemberCannotCreateOrSeeTeamPlansAndFormerAuthorLosesAccess(): void
    {
        $admin = $this->currentUser;
        $team = $this->team();
        $author = $this->member($team);
        $this->loginAs($author);
        $plan = $this->assertJsonResponse($this->postJson('/api/action-plans', $this->plan($team)), 201);
        static::getContainer()->get(TeamMembershipRepositoryInterface::class)->leave(Uuid::fromString($team), $author->id());
        self::assertSame([], $this->getJson('/api/action-plans')['plans']);
        self::assertSame(403, $this->postJson('/api/action-plans', $this->plan($team))->getStatusCode());
        self::assertSame(404, $this->putJson('/api/action-plans/'.$plan['id'], $this->plan(null))->getStatusCode());
        self::assertSame(404, $this->deleteJson('/api/action-plans/'.$plan['id'])->getStatusCode());
        $this->loginAs($admin);
        self::assertSame([$plan['id']], array_column($this->getJson('/api/action-plans')['plans'], 'id'));
    }

    public function testAuthorCanSwitchBetweenPrivateAndTeamScopes(): void
    {
        $team = $this->team();
        $plan = $this->assertJsonResponse($this->postJson('/api/action-plans', $this->plan(null)), 201);
        $shared = $this->assertJsonResponse($this->putJson('/api/action-plans/'.$plan['id'], $this->plan($team)));
        self::assertSame($team, $shared['teamId']);
        $private = $this->assertJsonResponse($this->putJson('/api/action-plans/'.$plan['id'], $this->plan(null)));
        self::assertNull($private['teamId']);
    }

    public function testTaskTypesOnlyAcceptPlansOfTheirOwnTeamAndOnlyAdminsCanAttachThem(): void
    {
        $admin = $this->currentUser;
        $team = $this->team();
        $otherTeam = $this->team();
        $private = $this->assertJsonResponse($this->postJson('/api/action-plans', $this->plan(null)), 201);
        $other = $this->assertJsonResponse($this->postJson('/api/action-plans', $this->plan($otherTeam)), 201);
        foreach ([$private['id'], $other['id'], Uuid::generate()->value()] as $planId) {
            self::assertSame(400, $this->postJson('/api/task-templates', $this->taskType($team, $planId))->getStatusCode());
        }
        $shared = $this->assertJsonResponse($this->postJson('/api/action-plans', $this->plan($team)), 201);
        $type = $this->assertJsonResponse($this->postJson('/api/task-templates', $this->taskType($team, $shared['id'])), 201);
        $member = $this->member($team);
        $this->loginAs($member);
        self::assertSame(403, $this->postJson('/api/task-templates', $this->taskType($team, $shared['id']))->getStatusCode());
        self::assertSame(403, $this->putJson('/api/task-templates/'.$type['id'], $this->taskType($team, null))->getStatusCode());
        $this->loginAs($admin);
        $payload = $this->taskType($team, null);
        unset($payload['actionPlanId']);
        self::assertSame($shared['id'], $this->assertJsonResponse($this->putJson('/api/task-templates/'.$type['id'], $payload))['actionPlanId']);
    }

    public function testLinkedPlansCannotBeDeletedOrMadePrivateUntilDetached(): void
    {
        $team = $this->team();
        $plan = $this->assertJsonResponse($this->postJson('/api/action-plans', $this->plan($team)), 201);
        $type = $this->assertJsonResponse($this->postJson('/api/task-templates', $this->taskType($team, $plan['id'])), 201);
        self::assertSame(400, $this->deleteJson('/api/action-plans/'.$plan['id'])->getStatusCode());
        self::assertSame(400, $this->putJson('/api/action-plans/'.$plan['id'], $this->plan(null))->getStatusCode());
        self::assertNull($this->assertJsonResponse($this->putJson('/api/task-templates/'.$type['id'], $this->taskType($team, null)))['actionPlanId']);
        self::assertSame(200, $this->putJson('/api/action-plans/'.$plan['id'], $this->plan(null))->getStatusCode());
        self::assertSame(204, $this->deleteJson('/api/action-plans/'.$plan['id'])->getStatusCode());
    }

    public function testTakenTaskKeepsItsPlanAndCompletionCanBeRetriedWithoutApprovingIt(): void
    {
        $admin = $this->currentUser;
        $team = $this->team();
        $plan = $this->assertJsonResponse($this->postJson('/api/action-plans', $this->plan($team)), 201);
        $type = $this->assertJsonResponse($this->postJson('/api/task-templates', $this->taskType($team, $plan['id'])), 201);
        $member = $this->member($team);
        $this->loginAs($member);
        $taken = $this->assertJsonResponse($this->postJson('/api/task-templates/'.$type['id'].'/take', []), 201);
        self::assertSame($plan['steps'], $taken['actionPlan']['steps']);
        self::assertSame('bell', $taken['actionPlan']['reminderSound']);
        $this->loginAs($admin);
        $changed = $this->plan($team);
        $changed['steps'] = [['name' => 'Different activity', 'stages' => []]];
        $changed['reminderSound'] = 'double';
        $this->assertJsonResponse($this->putJson('/api/action-plans/'.$plan['id'], $changed));
        self::assertSame(403, $this->postJson('/api/task-executions/'.$taken['id'].'/complete', [])->getStatusCode());
        $this->loginAs($member);
        self::assertSame($plan['steps'], $this->getJson('/api/task-executions/mine')['executions'][0]['actionPlan']['steps']);
        self::assertSame('bell', $this->getJson('/api/task-executions/mine')['executions'][0]['actionPlan']['reminderSound']);
        $done = $this->assertJsonResponse($this->postJson('/api/task-executions/'.$taken['id'].'/complete', []));
        self::assertSame('completed', $done['status']);
        self::assertNull($done['approvedAt']);
        $retry = $this->assertJsonResponse($this->postJson('/api/task-executions/'.$taken['id'].'/complete', []));
        self::assertSame($done['completedAt'], $retry['completedAt']);
        $this->loginAs($admin);
        self::assertContains($taken['id'], array_column($this->getJson('/api/task-executions/awaiting-approval')['executions'], 'id'));
    }

    public function testAdminAssignedTasksAlsoReceiveAPlanSnapshot(): void
    {
        $admin = $this->currentUser;
        $team = $this->team();
        $plan = $this->assertJsonResponse($this->postJson('/api/action-plans', $this->plan($team)), 201);
        $type = $this->assertJsonResponse($this->postJson('/api/task-templates', $this->taskType($team, $plan['id'])), 201);
        $member = $this->member($team);
        $this->loginAs($admin);
        $assigned = $this->assertJsonResponse($this->postJson('/api/task-templates/'.$type['id'].'/assign', ['userId' => $member->id()->value()]), 201);
        self::assertSame($plan['id'], $assigned['actionPlan']['id']);
    }
}
