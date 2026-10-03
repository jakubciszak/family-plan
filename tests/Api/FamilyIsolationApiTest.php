<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Command\CreateTeamCommand;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;

final class FamilyIsolationApiTest extends ApiTestCase
{
    private User $parentA;
    private User $childA;
    private User $parentB;
    private User $childB;
    private string $teamA;
    private string $teamB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parentA = $this->account('Parent A');
        $this->childA = $this->account('Child A');
        $this->parentB = $this->account('Parent B');
        $this->childB = $this->account('Child B');
        $this->teamA = $this->family($this->parentA, $this->childA);
        $this->teamB = $this->family($this->parentB, $this->childB);
        $this->loginAs($this->parentA);
    }

    public function testAnonymousRequestsCannotReadOrCreateAccounts(): void
    {
        $this->client->restart();
        foreach (['/api/users', '/api/tasks', '/api/bonus-rules'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseStatusCodeSame(401);
        }
        $this->assertSame(401, $this->postJson('/api/users', [
            'name' => 'Anonymous', 'email' => 'anonymous@example.com', 'password' => 'password123', 'role' => 'ROLE_ADMIN',
        ])->getStatusCode());
    }

    public function testDirectoryContainsOnlySelfAndFamily(): void
    {
        $ids = array_column($this->getJson('/api/users')['users'], 'id');
        $this->assertEqualsCanonicalizing([$this->parentA->id()->value(), $this->childA->id()->value()], $ids);
        $this->loginAs($this->childA);
        $this->assertEqualsCanonicalizing($ids, array_column($this->getJson('/api/users')['users'], 'id'));
        $this->assertSame($this->childA->id()->value(), $this->getJson('/api/users/' . $this->childA->id()->value())['id']);
    }

    public function testUnrelatedProfilesAndPointsAreHidden(): void
    {
        foreach (['', '/points'] as $suffix) {
            $this->client->request('GET', '/api/users/' . $this->childB->id()->value() . $suffix);
            $this->assertResponseStatusCodeSame(404);
        }
        $this->getJson('/api/users/' . $this->childA->id()->value() . '/points');
        $this->loginAs($this->childA);
        $this->getJson('/api/users/' . $this->childA->id()->value() . '/points');
        $this->client->request('GET', '/api/users/' . $this->parentA->id()->value() . '/points');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testFamilyAdminCannotCreateGlobalAccountsOrResetPasswords(): void
    {
        $email = 'escalation-' . Uuid::generate()->value() . '@example.com';
        $response = $this->postJson('/api/users', [
            'name' => 'Escalation', 'email' => $email, 'password' => 'password123', 'role' => 'ROLE_ADMIN',
        ]);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertNull(static::getContainer()->get(UserRepositoryInterface::class)->findByEmail(Email::fromString($email)));
        foreach ([$this->childA, $this->childB, $this->parentB] as $target) {
            $response = $this->postJson('/api/users/' . $target->id()->value() . '/reset-password', ['newPassword' => 'changed1234']);
            $this->assertSame(403, $response->getStatusCode());
            $saved = static::getContainer()->get(UserRepositoryInterface::class)->findById($target->id());
            $this->assertTrue(password_verify('password123', $saved->password()));
        }
    }

    public function testBonusReadAndWritesRequireTheRelevantMembership(): void
    {
        $rule = $this->rule();
        $url = '/api/bonus-rules/' . $rule['id'];
        $this->loginAs($this->parentB);
        $this->client->request('GET', $url);
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame(404, $this->putJson($url, $this->ruleData())->getStatusCode());
        foreach (['activate', 'deactivate'] as $action) {
            $this->assertSame(404, $this->postJson($url . '/' . $action, [])->getStatusCode());
        }
        $this->assertSame([], $this->getJson('/api/bonus-rules')['rules']);
        $this->loginAs($this->childA);
        $this->getJson($url);
        $this->assertSame(403, $this->putJson($url, $this->ruleData())->getStatusCode());
        foreach (['activate', 'deactivate'] as $action) {
            $this->assertSame(403, $this->postJson($url . '/' . $action, [])->getStatusCode());
        }
        $this->loginAs($this->parentA);
        $unchanged = $this->getJson($url);
        $this->assertSame($rule, $unchanged);
        $this->assertSame(200, $this->putJson($url, $this->ruleData())->getStatusCode());
        $this->assertSame(200, $this->postJson($url . '/deactivate', [])->getStatusCode());
        $this->assertSame(200, $this->postJson($url . '/activate', [])->getStatusCode());
    }

    public function testBonusCannotReferenceAnotherFamilysTaskType(): void
    {
        $this->loginAs($this->parentB);
        $template = $this->assertJsonResponse($this->postJson('/api/task-templates', [
            'teamId' => $this->teamB, 'name' => 'Foreign type', 'points' => 10, 'frequency' => 'daily',
        ]), 201);
        $this->loginAs($this->parentA);
        $rule = $this->rule();
        $data = $this->ruleData();
        $data['ruleType'] = 'consecutive_days';
        $data['ruleConfig'] = ['taskTemplateId' => $template['id'], 'requiredDays' => 3];
        $this->assertSame(404, $this->postJson('/api/bonus-rules', ['teamId' => $this->teamA] + $data)->getStatusCode());
        $this->assertSame(404, $this->putJson('/api/bonus-rules/' . $rule['id'], $data)->getStatusCode());
        $this->assertSame($rule, $this->getJson('/api/bonus-rules/' . $rule['id']));
        $own = $this->assertJsonResponse($this->postJson('/api/task-templates', [
            'teamId' => $this->teamA, 'name' => 'Own type', 'points' => 10, 'frequency' => 'daily',
        ]), 201);
        $data['ruleConfig']['taskTemplateId'] = $own['id'];
        $this->assertSame(200, $this->putJson('/api/bonus-rules/' . $rule['id'], $data)->getStatusCode());
    }

    public function testLeavingFamilyRevokesBonusAccess(): void
    {
        $rule = $this->rule();
        static::getContainer()->get(TeamMembershipRepositoryInterface::class)->leave(Uuid::fromString($this->teamA), $this->childA->id());
        $this->loginAs($this->childA);
        $this->client->request('GET', '/api/bonus-rules/' . $rule['id']);
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame([], $this->getJson('/api/bonus-rules')['rules']);
    }

    public function testTaskCreatorCannotBeSpoofed(): void
    {
        $this->loginAs($this->parentB);
        $response = $this->postJson('/api/tasks', [
            'name' => 'Spoofed', 'teamId' => $this->teamA, 'createdBy' => $this->parentA->id()->value(),
        ]);
        $this->assertSame(403, $response->getStatusCode());
        $this->loginAs($this->parentA);
        $this->assertSame([], $this->getJson('/api/tasks')['tasks']);
        $this->assertSame(201, $this->postJson('/api/tasks', ['name' => 'Own task', 'teamId' => $this->teamA])->getStatusCode());
    }

    public function testTaskCannotBeCreatedWithForeignAssignee(): void
    {
        $this->assertSame(403, $this->postJson('/api/tasks', [
            'name' => 'Foreign assignee', 'teamId' => $this->teamA, 'assignedUserId' => $this->childB->id()->value(),
        ])->getStatusCode());
    }

    public function testTaskAccessAssignmentAndApprovalStayWithinFamily(): void
    {
        $task = $this->assertJsonResponse($this->postJson('/api/tasks', [
            'name' => 'Own task', 'points' => 10, 'teamId' => $this->teamA, 'assignedUserId' => $this->childA->id()->value(),
        ]), 201);
        $url = '/api/tasks/' . $task['id'];
        $this->loginAs($this->parentB);
        $this->assertSame([], $this->getJson('/api/tasks?teamId=' . $this->teamA)['tasks']);
        $this->client->request('GET', $url);
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame(403, $this->postJson($url . '/assign', ['userId' => $this->childB->id()->value()])->getStatusCode());
        $this->assertSame(404, $this->postJson($url . '/complete', [])->getStatusCode());
        $this->loginAs($this->childA);
        $this->assertSame(200, $this->postJson($url . '/complete', [])->getStatusCode());
        $this->loginAs($this->parentB);
        $this->assertSame(403, $this->postJson($url . '/approve', [])->getStatusCode());
        $this->loginAs($this->parentA);
        $this->assertSame(200, $this->postJson($url . '/approve', [])->getStatusCode());
    }

    public function testRemovedAssigneeCannotCompleteOrUnassignTask(): void
    {
        $task = $this->assertJsonResponse($this->postJson('/api/tasks', [
            'name' => 'Own task', 'points' => 10, 'teamId' => $this->teamA, 'assignedUserId' => $this->childA->id()->value(),
        ]), 201);
        static::getContainer()->get(TeamMembershipRepositoryInterface::class)->leave(Uuid::fromString($this->teamA), $this->childA->id());
        $this->loginAs($this->childA);
        foreach (['complete', 'unassign'] as $action) {
            $this->assertSame(404, $this->postJson('/api/tasks/' . $task['id'] . '/' . $action, [])->getStatusCode());
        }
    }

    public function testNotificationSendingAndSettingsDoNotCrossFamilies(): void
    {
        $this->client->request('GET', '/api/user-settings/' . $this->childB->id()->value());
        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(403, $this->putJson('/api/user-settings/' . $this->childB->id()->value(), [
            'preference_type' => 'notifications', 'options' => ['push_enabled' => false],
        ])->getStatusCode());
        $this->assertSame(404, $this->postJson('/api/push/announcements', [
            'userId' => $this->childB->id()->value(), 'message' => 'Foreign message',
        ])->getStatusCode());
    }

    public function testSharedChildDoesNotExposeTasksFromTheirOtherHome(): void
    {
        static::getContainer()->get(TeamMembershipRepositoryInterface::class)->join(
            Uuid::fromString($this->teamB), $this->childA->id(), TeamRole::member()
        );
        $executionIds = [];
        foreach ([[$this->parentA, $this->teamA], [$this->parentB, $this->teamB]] as [$parent, $team]) {
            $this->loginAs($parent);
            $template = $this->assertJsonResponse($this->postJson('/api/task-templates', [
                'teamId' => $team, 'name' => 'Home task', 'points' => 10, 'frequency' => 'daily',
            ]), 201);
            $this->loginAs($this->childA);
            $execution = $this->assertJsonResponse($this->postJson('/api/task-templates/' . $template['id'] . '/take', []), 201);
            $executionIds[$team] = $execution['id'];
        }
        foreach ([[$this->parentA, $this->teamA], [$this->parentB, $this->teamB]] as [$parent, $team]) {
            $this->loginAs($parent);
            $executions = $this->getJson('/api/task-executions/of/' . $this->childA->id()->value())['executions'];
            $this->assertSame([$executionIds[$team]], array_column($executions, 'id'));
        }
        static::getContainer()->get(TeamMembershipRepositoryInterface::class)->leave(Uuid::fromString($this->teamA), $this->childA->id());
        $this->loginAs($this->childA);
        $this->assertSame(403, $this->postJson('/api/task-executions/' . $executionIds[$this->teamA] . '/abandon', [])->getStatusCode());
        $this->assertSame(204, $this->postJson('/api/task-executions/' . $executionIds[$this->teamB] . '/abandon', [])->getStatusCode());
    }

    private function rule(): array
    {
        $this->assertSame(201, $this->postJson('/api/bonus-rules', ['teamId' => $this->teamA] + $this->ruleData())->getStatusCode());
        return $this->getJson('/api/bonus-rules')['rules'][0];
    }

    private function ruleData(): array
    {
        return ['name' => 'Bonus', 'description' => 'Family bonus', 'bonusPoints' => 10,
            'ruleType' => 'monthly_task_count', 'ruleConfig' => ['requiredCount' => 3]];
    }

    private function account(string $name): User
    {
        $user = User::create(Uuid::generate(), $name, Email::fromString(Uuid::generate()->value() . '@example.com'), password_hash('password123', PASSWORD_BCRYPT), Role::USER);
        static::getContainer()->get(UserRepositoryInterface::class)->save($user);
        return $user;
    }

    private function family(User $parent, User $child): string
    {
        $id = Uuid::generate();
        static::getContainer()->get('command.bus')->dispatch(new CreateTeamCommand($id->value(), 'Family', null, $parent->id()->value()));
        static::getContainer()->get(TeamMembershipRepositoryInterface::class)->join($id, $child->id(), TeamRole::member());
        return $id->value();
    }
}
