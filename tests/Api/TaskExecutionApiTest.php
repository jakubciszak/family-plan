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

class TaskExecutionApiTest extends ApiTestCase
{
    public function testTakingATypeCreatesMyOwnTask(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'per_day', 'count' => 3]);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );

        $this->assertSame($type['id'], $taken['taskTemplateId']);
        $this->assertSame($context['member']->id()->value(), $taken['assignedUserId']);

        $mine = $this->getJson('/api/task-executions/mine')['executions'];
        $this->assertCount(1, $mine);
        $this->assertSame($taken['id'], $mine[0]['id']);
    }

    public function testTakingSpendsOneRunFromTheTeamPool(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'per_day', 'count' => 2]);

        $this->loginAs($context['member']);
        $this->postJson("/api/task-templates/{$type['id']}/take", []);

        $this->assertSame(1, $this->typeById($type['id'])['remaining']);
    }

    public function testPoolIsSharedByTheWholeTeam(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'per_day', 'count' => 1]);

        $this->loginAs($context['member']);
        $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );

        $this->loginAs($context['admin']);
        $refused = $this->postJson("/api/task-templates/{$type['id']}/take", []);

        $this->assertSame(Response::HTTP_CONFLICT, $refused->getStatusCode());
        $this->assertSame(0, $this->typeById($type['id'])['remaining']);
    }

    public function testUnlimitedTypeCanBeTakenRepeatedly(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['member']);
        foreach (range(1, 3) as $ignored) {
            $this->assertJsonResponse(
                $this->postJson("/api/task-templates/{$type['id']}/take", []),
                Response::HTTP_CREATED
            );
        }

        $this->assertCount(3, $this->getJson('/api/task-executions/mine')['executions']);
    }

    public function testAbandoningGivesTheRunBack(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'once']);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->assertSame(0, $this->typeById($type['id'])['remaining']);

        $this->assertSame(
            Response::HTTP_NO_CONTENT,
            $this->postJson("/api/task-executions/{$taken['id']}/abandon", [])->getStatusCode()
        );

        $this->assertSame(1, $this->typeById($type['id'])['remaining']);
        $this->assertCount(0, $this->getJson('/api/task-executions/mine')['executions']);
    }

    public function testFullRunEarnsPoints(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], 40);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($context['admin']);
        $this->assertSame(
            Response::HTTP_OK,
            $this->postJson("/api/task-executions/{$taken['id']}/approve", [])->getStatusCode()
        );

        $points = $this->getJson('/api/users/' . $context['member']->id()->value() . '/points');
        $this->assertSame(40, $points['balance']);
    }

    public function testOnlyTheAssigneeCompletesTheirRun(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );

        $this->loginAs($context['admin']);
        $this->assertSame(
            Response::HTTP_FORBIDDEN,
            $this->postJson("/api/task-executions/{$taken['id']}/complete", [])->getStatusCode()
        );
    }

    public function testMemberCannotApproveTheirOwnRun(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->assertSame(
            Response::HTTP_FORBIDDEN,
            $this->postJson("/api/task-executions/{$taken['id']}/approve", [])->getStatusCode()
        );
    }

    public function testOutsiderCannotTakeATypeOfAnotherTeam(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($this->authenticate(Role::USER));

        $this->assertSame(
            Response::HTTP_FORBIDDEN,
            $this->postJson("/api/task-templates/{$type['id']}/take", [])->getStatusCode()
        );
    }

    public function testRetiredTypeCannotBeTaken(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);
        $this->postJson("/api/task-templates/{$type['id']}/deactivate", []);

        $this->loginAs($context['member']);

        $this->assertSame(
            Response::HTTP_CONFLICT,
            $this->postJson("/api/task-templates/{$type['id']}/take", [])->getStatusCode()
        );
    }

    public function testTeamAdminSeesFinishedTasksAwaitingApproval(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->assertCount(0, $this->getJson('/api/task-executions/awaiting-approval')['executions']);
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($context['admin']);
        $awaiting = $this->getJson('/api/task-executions/awaiting-approval')['executions'];

        $this->assertCount(1, $awaiting);
        $this->assertSame($taken['id'], $awaiting[0]['id']);
        $this->assertSame('Dziecko', $awaiting[0]['assignedUserName']);
    }

    public function testApprovedTaskLeavesTheApprovalQueue(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($context['admin']);
        $this->postJson("/api/task-executions/{$taken['id']}/approve", []);

        $this->assertCount(0, $this->getJson('/api/task-executions/awaiting-approval')['executions']);
    }

    public function testApprovalQueueIsScopedToTeamsIAdminister(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($this->authenticate(Role::USER));

        $this->assertCount(0, $this->getJson('/api/task-executions/awaiting-approval')['executions']);
    }

    private function defineType(string $teamId, array $limit, int $points = 10): array
    {
        return $this->assertJsonResponse(
            $this->postJson('/api/task-templates', [
                'teamId' => $teamId,
                'name' => 'Odkurzyc ' . uniqid(),
                'description' => 'opis',
                'points' => $points,
                'frequency' => 'daily',
                'executionLimit' => $limit,
            ]),
            Response::HTTP_CREATED
        );
    }

    private function typeById(string $id): array
    {
        foreach ($this->getJson('/api/task-templates')['templates'] as $template) {
            if ($template['id'] === $id) {
                return $template;
            }
        }

        $this->fail('Task type not found in the listing');
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
}
