<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\TeamManagement\Application\Command\CreateTeamCommand;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\HttpFoundation\Response;

class TaskAssignmentPermissionsTest extends ApiTestCase
{
    public function testTeamAdminAssignsAnotherPerson(): void
    {
        ['teamId' => $teamId, 'admin' => $admin, 'member' => $member] = $this->teamWithMember();
        $taskId = $this->createTask($teamId, $admin->id()->value());

        $response = $this->postJson("/api/tasks/{$taskId}/assign", ['userId' => $member->id()->value()]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
    }

    public function testMemberAssignsOnlyThemselves(): void
    {
        ['teamId' => $teamId, 'admin' => $admin, 'member' => $member] = $this->teamWithMember();
        $taskId = $this->createTask($teamId, $admin->id()->value());

        $this->loginAs($member);
        $own = $this->postJson("/api/tasks/{$taskId}/assign", ['userId' => $member->id()->value()]);
        $this->assertSame(Response::HTTP_OK, $own->getStatusCode(), $own->getContent());

        $this->postJson("/api/tasks/{$taskId}/unassign", []);

        $somebodyElse = $this->postJson("/api/tasks/{$taskId}/assign", ['userId' => $admin->id()->value()]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $somebodyElse->getStatusCode());
    }

    public function testAssigneeCanStepAwayWhileTheTaskIsNotDone(): void
    {
        ['teamId' => $teamId, 'admin' => $admin, 'member' => $member] = $this->teamWithMember();
        $taskId = $this->createTask($teamId, $admin->id()->value());
        $this->postJson("/api/tasks/{$taskId}/assign", ['userId' => $member->id()->value()]);

        $this->loginAs($member);
        $response = $this->postJson("/api/tasks/{$taskId}/unassign", []);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $this->assertNull($this->getJson("/api/tasks/{$taskId}")['assignedUserId']);
    }

    public function testFinishedTaskCannotBeAbandoned(): void
    {
        ['teamId' => $teamId, 'admin' => $admin, 'member' => $member] = $this->teamWithMember();
        $taskId = $this->createTask($teamId, $admin->id()->value());
        $this->postJson("/api/tasks/{$taskId}/assign", ['userId' => $member->id()->value()]);

        $this->loginAs($member);
        $this->postJson("/api/tasks/{$taskId}/complete", []);

        $response = $this->postJson("/api/tasks/{$taskId}/unassign", []);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testOutsiderCannotAssignThemselves(): void
    {
        ['teamId' => $teamId, 'admin' => $admin] = $this->teamWithMember();
        $taskId = $this->createTask($teamId, $admin->id()->value());

        $outsider = $this->authenticate(Role::USER);
        $this->loginAs($outsider);

        $response = $this->postJson("/api/tasks/{$taskId}/assign", ['userId' => $outsider->id()->value()]);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    private function teamWithMember(): array
    {
        $admin = $this->currentUser;

        $teamId = Uuid::generate()->value();
        static::getContainer()->get('command.bus')->dispatch(
            new CreateTeamCommand($teamId, 'Rodzina', null, $admin->id()->value())
        );

        $member = User::create(
            Uuid::generate(),
            'Dziecko',
            Email::fromString(sprintf('dziecko-%s@example.com', uniqid())),
            password_hash('password123', PASSWORD_BCRYPT),
            Role::USER
        );
        static::getContainer()->get(UserRepositoryInterface::class)->save($member);

        $invite = $this->assertJsonResponse(
            $this->postJson("/api/teams/{$teamId}/invite", [
                'email' => $member->email()->value(),
                'role' => 'member',
            ]),
            Response::HTTP_CREATED
        );

        $this->loginAs($member);
        $this->postJson("/api/teams/invitations/{$invite['invitation']['token']}/accept", []);
        $this->loginAs($admin);

        return ['teamId' => $teamId, 'admin' => $admin, 'member' => $member];
    }

    private function createTask(string $teamId, string $createdBy): string
    {
        $task = $this->assertJsonResponse(
            $this->postJson('/api/tasks', [
                'name' => 'Odkurzyc',
                'points' => 20,
                'frequency' => 'weekly',
                'teamId' => $teamId,
                'createdBy' => $createdBy,
            ]),
            Response::HTTP_CREATED
        );

        return $task['id'];
    }
}
