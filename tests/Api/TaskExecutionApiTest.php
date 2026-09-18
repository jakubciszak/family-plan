<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Party\Domain\Repository\SignatureRepositoryInterface;
use App\Party\Domain\ValueObject\ResponsibilityType;
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

    public function testApprovingARunPaysOutTheBonusItUnlocks(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], 16);

        $this->assertJsonResponse(
            $this->postJson('/api/bonus-rules', [
                'teamId' => $context['teamId'],
                'name' => 'Minimum 15 punktow w tygodniu',
                'description' => 'Zbierz 15 punktow za zadania w ciagu tygodnia',
                'bonusPoints' => 5,
                'ruleType' => 'weekly_points_sum',
                'ruleConfig' => ['requiredPoints' => 15, 'accounts' => ['tasks']],
            ]),
            Response::HTTP_CREATED
        );

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($context['admin']);
        $this->postJson("/api/task-executions/{$taken['id']}/approve", []);

        $points = $this->getJson('/api/users/' . $context['member']->id()->value() . '/points');

        $this->assertSame(21, $points['balance'], 'Zadanie daje 16 punktow, regula dokłada 5 bonusowych');
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

    public function testABacklogEntryLandsOnTheDayItWasReallyDone(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );

        $threeDaysBack = (new \DateTimeImmutable('-3 days'))->format('Y-m-d');
        $done = $this->assertJsonResponse(
            $this->postJson("/api/task-executions/{$taken['id']}/complete", ['doneOn' => $threeDaysBack])
        );

        $this->assertSame($threeDaysBack, substr($done['completedAt'], 0, 10));
    }

    public function testABacklogEntryReachesSevenDaysBackAtMost(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );

        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->postJson("/api/task-executions/{$taken['id']}/complete", [
                'doneOn' => (new \DateTimeImmutable('-8 days'))->format('Y-m-d'),
            ])->getStatusCode()
        );

        $this->assertSame('new', $this->getJson('/api/task-executions/mine')['executions'][0]['status']);
    }

    public function testABacklogEntryCannotBeDatedInTheFuture(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );

        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->postJson("/api/task-executions/{$taken['id']}/complete", [
                'doneOn' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d'),
            ])->getStatusCode()
        );
    }

    public function testAMalformedDayIsRefused(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );

        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->postJson("/api/task-executions/{$taken['id']}/complete", [
                'doneOn' => 'wczoraj',
            ])->getStatusCode()
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

    public function testEveryStepOfTheCycleIsSigned(): void
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

        $signatures = static::getContainer()
            ->get(SignatureRepositoryInterface::class)
            ->findBySubject(Uuid::fromString($taken['id']));

        $acts = array_map(fn ($signature) => $signature->act()->value(), $signatures);
        $signatories = array_map(fn ($signature) => $signature->signatoryPartyId()->value(), $signatures);

        $this->assertSame(
            [ResponsibilityType::TAKE_TASK, ResponsibilityType::COMPLETE_TASK, ResponsibilityType::APPROVE_TASK],
            $acts
        );
        $this->assertSame(
            [
                $context['member']->id()->value(),
                $context['member']->id()->value(),
                $context['admin']->id()->value(),
            ],
            $signatories
        );
    }

    public function testLeaderboardRanksMembersByThePointsTheyBookedThisWeek(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], 12);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($context['admin']);
        $this->postJson("/api/task-executions/{$taken['id']}/approve", []);

        $board = $this->getJson('/api/points/leaderboard?teamId=' . $context['teamId']);

        $this->assertCount(7, $board['days']);
        $this->assertCount(1, $board['standings'], 'Admini nie staja w szranki, wiec zostaje sam czlonek');
        $this->assertSame($context['member']->id()->value(), $board['standings'][0]['userId']);
        $this->assertSame(12, $board['standings'][0]['total']);
        $this->assertSame(12, $board['standings'][0]['perDay'][$board['today']]);
    }

    public function testTheWeekShowsBonusPointsApartFromTaskPoints(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], 16);

        $this->assertJsonResponse(
            $this->postJson('/api/bonus-rules', [
                'teamId' => $context['teamId'],
                'name' => 'Minimum 15 punktow w tygodniu',
                'description' => 'Zbierz 15 punktow za zadania w ciagu tygodnia',
                'bonusPoints' => 5,
                'ruleType' => 'weekly_points_sum',
                'ruleConfig' => ['requiredPoints' => 15, 'accounts' => ['tasks']],
            ]),
            Response::HTTP_CREATED
        );

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($context['admin']);
        $this->postJson("/api/task-executions/{$taken['id']}/approve", []);

        $this->loginAs($context['member']);
        $week = $this->getJson('/api/points/week');

        $today = array_values(array_filter($week['days'], static fn (array $day) => $day['isToday']))[0];

        $this->assertSame(16, $today['points'], 'Punkty za zadanie zostaja osobno');
        $this->assertSame(5, $today['bonus'], 'Bonus jest pokazany obok, nie doliczony do punktow za zadanie');
        $this->assertSame(5, $week['bonusTotal']);
    }

    public function testADayNamesTheRuleThatPaidTheBonus(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], 16);

        $this->assertJsonResponse(
            $this->postJson('/api/bonus-rules', [
                'teamId' => $context['teamId'],
                'name' => 'Minimum 15 punktow w tygodniu',
                'description' => 'Zbierz 15 punktow za zadania w ciagu tygodnia',
                'bonusPoints' => 5,
                'ruleType' => 'weekly_points_sum',
                'ruleConfig' => ['requiredPoints' => 15, 'accounts' => ['tasks']],
            ]),
            Response::HTTP_CREATED
        );

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($context['admin']);
        $this->postJson("/api/task-executions/{$taken['id']}/approve", []);

        $this->loginAs($context['member']);
        $day = $this->getJson('/api/points/day?date=' . (new \DateTimeImmutable())->format('Y-m-d'));

        $this->assertCount(1, $day['bonuses']);
        $this->assertSame(5, $day['bonuses'][0]['points']);
        $this->assertStringContainsString('Minimum 15 punktow w tygodniu', $day['bonuses'][0]['name']);
        $this->assertNotNull($day['bonuses'][0]['ruleId']);
    }

    public function testAnAdminTakesBackABonusTheyDoNotStandBehind(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], 16);

        $this->assertJsonResponse(
            $this->postJson('/api/bonus-rules', [
                'teamId' => $context['teamId'],
                'name' => 'Minimum 15 punktow w tygodniu',
                'description' => 'Zbierz 15 punktow za zadania w ciagu tygodnia',
                'bonusPoints' => 5,
                'ruleType' => 'weekly_points_sum',
                'ruleConfig' => ['requiredPoints' => 15, 'accounts' => ['tasks']],
            ]),
            Response::HTTP_CREATED
        );

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($context['admin']);
        $this->postJson("/api/task-executions/{$taken['id']}/approve", []);

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $memberId = $context['member']->id()->value();
        $bonus = $this->getJson("/api/points/day?date={$today}&userId={$memberId}")['bonuses'][0];

        $this->assertJsonResponse(
            $this->deleteJson("/api/points/bonuses/{$bonus['id']}?userId={$memberId}"),
            Response::HTTP_OK
        );

        $day = $this->getJson("/api/points/day?date={$today}&userId={$memberId}");

        $this->assertSame(0, $day['bonus'], 'Bonus i jego cofniecie znosza sie tego samego dnia');
        $this->assertCount(2, $day['bonuses'], 'Historia zostaje: wyplata i cofniecie');
        $this->assertTrue($day['bonuses'][0]['takenBack'], 'Cofnietego bonusu nie da sie cofnac drugi raz');

        $this->deleteJson("/api/points/bonuses/{$bonus['id']}?userId={$memberId}");

        $this->assertSame(
            0,
            $this->getJson("/api/points/day?date={$today}&userId={$memberId}")['bonus'],
            'Drugie cofniecie nie zabiera punktow po raz kolejny'
        );
    }

    public function testAMemberCannotTakeBackTheirOwnBonus(): void
    {
        $context = $this->teamWithMember();
        $memberId = $context['member']->id()->value();

        $this->loginAs($context['member']);

        $this->assertSame(
            Response::HTTP_FORBIDDEN,
            $this->deleteJson(sprintf('/api/points/bonuses/%s?userId=%s', Uuid::generate()->value(), $memberId))
                ->getStatusCode()
        );
    }

    public function testOpeningADayListsWhatWasDoneAndWhatItEarned(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], 7);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($context['admin']);
        $this->postJson("/api/task-executions/{$taken['id']}/approve", []);

        $this->loginAs($context['member']);
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $day = $this->getJson('/api/points/day?date=' . $today);

        $this->assertSame(7, $day['total']);
        $this->assertCount(1, $day['tasks']);
        $this->assertSame($type['name'], $day['tasks'][0]['name']);
        $this->assertSame(7, $day['tasks'][0]['points']);
    }

    public function testAdminOpensTheWeekAndTheTasksOfTheirMember(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], 9);

        $this->loginAs($context['member']);
        $this->postJson("/api/task-templates/{$type['id']}/take", []);

        $this->loginAs($context['admin']);
        $memberId = $context['member']->id()->value();

        $week = $this->getJson('/api/points/week?userId=' . $memberId);
        $this->assertCount(7, $week['days']);

        $theirs = $this->getJson('/api/task-executions/of/' . $memberId);
        $this->assertCount(1, $theirs['executions']);
        $this->assertSame($memberId, $theirs['executions'][0]['assignedUserId']);
    }

    public function testAMemberCannotOpenAnotherMembersWeek(): void
    {
        $context = $this->teamWithMember();

        $this->loginAs($context['member']);
        $this->client->request('GET', '/api/points/week?userId=' . $context['admin']->id()->value());

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRejectingAFinishedTaskAwardsNothing(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], 30);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($context['admin']);
        $rejected = $this->assertJsonResponse(
            $this->postJson("/api/task-executions/{$taken['id']}/reject", ['reason' => 'Zlew zostal brudny']),
            Response::HTTP_OK
        );

        $this->assertSame('rejected', $rejected['status']);
        $this->assertSame('Zlew zostal brudny', $rejected['rejectionReason']);

        $points = $this->getJson('/api/users/' . $context['member']->id()->value() . '/points');
        $this->assertSame(0, $points['balance'], 'Odrzucone wykonanie nie daje punktow');
    }

    public function testARejectionWithoutAReasonIsRefused(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], 4);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($context['admin']);
        $this->postJson("/api/task-executions/{$taken['id']}/reject", ['reason' => '   ']);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testARejectedTaskGoesBackForApprovalAndCanEarnItsPoints(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], 11);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );
        $this->postJson("/api/task-executions/{$taken['id']}/complete", []);

        $this->loginAs($context['admin']);
        $this->postJson("/api/task-executions/{$taken['id']}/reject", ['reason' => 'Popraw']);

        $this->loginAs($context['member']);
        $mine = $this->getJson('/api/task-executions/mine')['executions'];
        $rejected = array_values(array_filter($mine, static fn (array $e) => $e['id'] === $taken['id']))[0];
        $this->assertSame('rejected', $rejected['status']);
        $this->assertSame('Popraw', $rejected['rejectionReason'], 'Czlonek widzi powod przy swoim zadaniu');

        $again = $this->assertJsonResponse(
            $this->postJson("/api/task-executions/{$taken['id']}/complete", []),
            Response::HTTP_OK
        );
        $this->assertSame('completed', $again['status'], 'Odrzucone zadanie wraca do akceptacji');

        $this->loginAs($context['admin']);
        $this->postJson("/api/task-executions/{$taken['id']}/approve", []);

        $points = $this->getJson('/api/users/' . $context['member']->id()->value() . '/points');
        $this->assertSame(11, $points['balance']);
    }

    public function testLeaderboardIsClosedToOutsiders(): void
    {
        $context = $this->teamWithMember();

        $outsider = User::create(
            Uuid::generate(),
            'Obcy',
            Email::fromString(sprintf('obcy-%s@example.com', uniqid())),
            password_hash('password123', PASSWORD_BCRYPT),
            Role::USER
        );
        static::getContainer()->get(UserRepositoryInterface::class)->save($outsider);
        $this->loginAs($outsider);

        $this->client->request('GET', '/api/points/leaderboard?teamId=' . $context['teamId']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
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

    public function testNothingIsBookedIntoAWeekThatHasAlreadyBeenSettled(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['member']);
        $taken = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/take", []),
            Response::HTTP_CREATED
        );

        $aWeekBack = (new \DateTimeImmutable('-7 days'))->format('Y-m-d');

        $this->loginAs($context['admin']);
        $this->assertJsonResponse($this->postJson('/api/allowance/weeks/close', [
            'userId' => $context['member']->id()->value(),
            'weekStart' => $aWeekBack,
        ]));

        $this->loginAs($context['member']);

        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->postJson("/api/task-executions/{$taken['id']}/complete", ['doneOn' => $aWeekBack])->getStatusCode()
        );

        $this->assertSame('new', $this->getJson('/api/task-executions/mine')['executions'][0]['status']);
    }

    public function testAnAdminHandsATaskTypeToAMember(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $given = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/assign", [
                'userId' => $context['member']->id()->value(),
            ]),
            Response::HTTP_CREATED
        );

        $this->assertSame($context['member']->id()->value(), $given['assignedUserId']);
        $this->assertSame('new', $given['status']);

        $this->loginAs($context['member']);
        $mine = $this->getJson('/api/task-executions/mine')['executions'];

        $this->assertCount(1, $mine);
        $this->assertSame($given['id'], $mine[0]['id']);
    }

    public function testOnlyAnAdminOfTheTeamHandsOutItsTasks(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['member']);

        $this->assertSame(
            Response::HTTP_FORBIDDEN,
            $this->postJson("/api/task-templates/{$type['id']}/assign", [
                'userId' => $context['member']->id()->value(),
            ])->getStatusCode()
        );
    }

    public function testAnAdminWritesDownATaskAMemberAlreadyDidAndThePointsAreAwarded(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], points: 30);
        $threeDaysBack = (new \DateTimeImmutable('-3 days'))->format('Y-m-d');

        $booked = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/book", [
                'userId' => $context['member']->id()->value(),
                'doneOn' => $threeDaysBack,
            ]),
            Response::HTTP_CREATED
        );

        $this->assertSame('approved', $booked['status']);
        $this->assertSame($threeDaysBack, substr($booked['completedAt'], 0, 10));

        $this->loginAs($context['member']);
        $week = $this->getJson('/api/points/week?weekStart=' . $threeDaysBack);
        $day = array_values(array_filter($week['days'], static fn (array $day) => $day['date'] === $threeDaysBack));

        $this->assertSame(30, $day[0]['points']);
    }

    public function testTheStandingsPutABackloggedTaskOnTheDayItWasDoneNotTheDayItWasWrittenDown(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], points: 30);
        $lastSunday = (new \DateTimeImmutable('monday this week'))->modify('-1 day')->format('Y-m-d');

        $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/book", [
                'userId' => $context['member']->id()->value(),
                'doneOn' => $lastSunday,
            ]),
            Response::HTTP_CREATED
        );

        $lastWeek = $this->getJson('/api/points/leaderboard?teamId=' . $context['teamId'] . '&weekStart=' . $lastSunday);
        $thisWeek = $this->getJson('/api/points/leaderboard?teamId=' . $context['teamId']);

        $this->assertSame(30, $lastWeek['standings'][0]['total']);
        $this->assertSame(30, $lastWeek['standings'][0]['perDay'][$lastSunday]);
        $this->assertSame(0, $thisWeek['standings'][0]['total']);
    }

    public function testATaskWrittenDownWithoutADayLandsOnToday(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $booked = $this->assertJsonResponse(
            $this->postJson("/api/task-templates/{$type['id']}/book", [
                'userId' => $context['member']->id()->value(),
            ]),
            Response::HTTP_CREATED
        );

        $this->assertSame(
            (new \DateTimeImmutable())->format('Y-m-d'),
            substr($booked['completedAt'], 0, 10)
        );
    }

    public function testATaskCannotBeWrittenDownIntoASettledWeek(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);
        $aWeekBack = (new \DateTimeImmutable('-7 days'))->format('Y-m-d');

        $this->assertJsonResponse($this->postJson('/api/allowance/weeks/close', [
            'userId' => $context['member']->id()->value(),
            'weekStart' => $aWeekBack,
        ]));

        $this->assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->postJson("/api/task-templates/{$type['id']}/book", [
                'userId' => $context['member']->id()->value(),
                'doneOn' => $aWeekBack,
            ])->getStatusCode()
        );
    }

    public function testATaskIsNotWrittenDownForSomeoneOutsideTheTeam(): void
    {
        $context = $this->teamWithMember();
        $other = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);

        $this->loginAs($context['admin']);

        $this->assertSame(
            Response::HTTP_FORBIDDEN,
            $this->postJson("/api/task-templates/{$type['id']}/book", [
                'userId' => $other['member']->id()->value(),
            ])->getStatusCode()
        );
    }

    public function testAdminMovesAndRemovesApprovedExecution(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited'], 30);
        $memberId = $context['member']->id()->value();
        $sunday = new \DateTimeImmutable('last sunday');
        $saturday = $sunday->modify('-1 day')->format('Y-m-d');
        $booked = $this->assertJsonResponse($this->postJson("/api/task-templates/{$type['id']}/book", [
            'userId' => $memberId,
            'doneOn' => $sunday->format('Y-m-d'),
        ]), Response::HTTP_CREATED);

        $moved = $this->assertJsonResponse($this->putJson("/api/task-executions/{$booked['id']}", ['doneOn' => $saturday]));
        $this->assertSame($saturday, substr($moved['completedAt'], 0, 10));
        $this->assertSame(30, $this->getJson("/api/users/{$memberId}/points")['balance']);
        $this->assertSame(30, $this->getJson("/api/points/day?userId={$memberId}&date={$saturday}")['total']);
        $this->assertSame(0, $this->getJson('/api/points/day?userId=' . $memberId . '&date=' . $sunday->format('Y-m-d'))['total']);

        $this->assertSame(Response::HTTP_NO_CONTENT, $this->deleteJson("/api/task-executions/{$booked['id']}")->getStatusCode());
        $this->assertSame(0, $this->getJson("/api/users/{$memberId}/points")['balance']);
        $this->assertSame([], $this->getJson("/api/points/day?userId={$memberId}&date={$saturday}")['tasks']);
        $this->assertSame(Response::HTTP_NOT_FOUND, $this->deleteJson("/api/task-executions/{$booked['id']}")->getStatusCode());
        $this->assertSame(0, $this->getJson("/api/users/{$memberId}/points")['balance']);
    }

    public function testExecutionCorrectionsRejectOtherWeeksInvalidDatesAndMembers(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);
        $day = new \DateTimeImmutable('last sunday');
        $booked = $this->assertJsonResponse($this->postJson("/api/task-templates/{$type['id']}/book", [
            'userId' => $context['member']->id()->value(),
            'doneOn' => $day->format('Y-m-d'),
        ]), Response::HTTP_CREATED);

        foreach ([$day->modify('+1 day')->format('Y-m-d'), '2026-02-30', '', null, 123] as $invalid) {
            $this->assertSame(Response::HTTP_BAD_REQUEST, $this->putJson("/api/task-executions/{$booked['id']}", ['doneOn' => $invalid])->getStatusCode());
        }

        $this->loginAs($context['member']);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->deleteJson("/api/task-executions/{$booked['id']}")->getStatusCode());
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->putJson("/api/task-executions/{$booked['id']}", ['doneOn' => $day->format('Y-m-d')])->getStatusCode());
    }

    public function testClosedWeekBlocksBothExecutionCorrections(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);
        $memberId = $context['member']->id()->value();
        $day = (new \DateTimeImmutable('last sunday'))->format('Y-m-d');
        $booked = $this->assertJsonResponse($this->postJson("/api/task-templates/{$type['id']}/book", [
            'userId' => $memberId,
            'doneOn' => $day,
        ]), Response::HTTP_CREATED);
        $this->assertJsonResponse($this->postJson('/api/allowance/weeks/close', ['userId' => $memberId, 'weekStart' => $day]));

        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->putJson("/api/task-executions/{$booked['id']}", ['doneOn' => $day])->getStatusCode());
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->deleteJson("/api/task-executions/{$booked['id']}")->getStatusCode());
        $detail = $this->getJson("/api/points/day?userId={$memberId}&date={$day}");
        $this->assertTrue($detail['closed']);
        $this->assertCount(1, $detail['tasks']);
    }

    public function testUnapprovedExecutionCannotBeMovedOrDeleted(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);
        $given = $this->assertJsonResponse($this->postJson("/api/task-templates/{$type['id']}/assign", [
            'userId' => $context['member']->id()->value(),
        ]), Response::HTTP_CREATED);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->deleteJson("/api/task-executions/{$given['id']}")->getStatusCode());
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->putJson("/api/task-executions/{$given['id']}", [
            'doneOn' => (new \DateTimeImmutable())->format('Y-m-d'),
        ])->getStatusCode());
    }

    public function testAnUnrelatedAdminCannotCorrectAnExecution(): void
    {
        $context = $this->teamWithMember();
        $type = $this->defineType($context['teamId'], ['type' => 'unlimited']);
        $booked = $this->assertJsonResponse($this->postJson("/api/task-templates/{$type['id']}/book", [
            'userId' => $context['member']->id()->value(),
        ]), Response::HTTP_CREATED);
        $this->authenticate();

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->deleteJson("/api/task-executions/{$booked['id']}")->getStatusCode());
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->putJson("/api/task-executions/{$booked['id']}", [
            'doneOn' => (new \DateTimeImmutable())->format('Y-m-d'),
        ])->getStatusCode());
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
