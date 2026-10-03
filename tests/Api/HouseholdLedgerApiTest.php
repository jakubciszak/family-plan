<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Allowance\Domain\Repository\PayoutRepositoryInterface;
use App\PointsManagement\Domain\Service\PointsLedger;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\PointsManagement\Domain\ValueObject\EntrySource;
use App\Shared\Domain\ValueObject\Uuid;
use App\Shared\Infrastructure\Clock\FixedClock;
use App\TaskManagement\Domain\Entity\TaskExecution;
use App\TaskManagement\Domain\Entity\TaskTemplate;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Repository\TaskTemplateRepositoryInterface;
use App\TaskManagement\Domain\ValueObject\Frequency;
use App\TaskManagement\Domain\ValueObject\Points;
use App\TaskManagement\Domain\ValueObject\ScheduleConfig;
use App\TaskManagement\Domain\ValueObject\TaskName;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\ValueObject\TeamRole;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use DateTimeImmutable;

final class HouseholdLedgerApiTest extends ApiTestCase
{
    private array $homeA;
    private array $homeB;
    private User $child;
    private string $week;

    protected function setUp(): void
    {
        parent::setUp();
        $this->homeA = $this->home();
        $this->homeB = $this->home();
        $this->child = User::create(Uuid::generate(), 'Shared child', Email::fromString(Uuid::generate()->value() . '@example.com'), password_hash('password123', PASSWORD_BCRYPT), Role::USER);
        static::getContainer()->get(UserRepositoryInterface::class)->save($this->child);
        foreach ([$this->homeA, $this->homeB] as $home) {
            static::getContainer()->get(TeamMembershipRepositoryInterface::class)->join(Uuid::fromString($home['teamId']), $this->child->id(), TeamRole::member());
        }
        $this->week = (new DateTimeImmutable('monday last week'))->format('Y-m-d');
    }

    public function testPointsCalendarsLeaderboardAndBonusReversalAreScoped(): void
    {
        $this->earn($this->homeA, 10);
        $this->earn($this->homeB, 40);
        $this->bonus($this->homeA, 3);
        $this->bonus($this->homeB, 7);
        foreach ([[$this->homeA, 10, 3], [$this->homeB, 40, 7]] as [$home, $tasks, $bonus]) {
            $this->loginAs($home['user']);
            $data = $this->getJson($this->url('/api/points/week', $home) . '&weekStart=' . $this->week);
            $this->assertSame($tasks, $data['total']);
            $this->assertSame($bonus, $data['bonusTotal']);
            $data = $this->getJson($this->url('/api/points/leaderboard', $home) . '&weekStart=' . $this->week);
            $this->assertSame($tasks + $bonus, $data['standings'][0]['total']);
            $data = $this->getJson('/api/users/' . $this->child->id()->value() . '/points?teamId=' . $home['teamId']);
            $this->assertSame($tasks + $bonus, $data['balance']);
        }
        $foreign = $this->getJson($this->url('/api/points/day', $this->homeB) . '&date=' . $this->week)['bonuses'][0]['id'];
        $this->loginAs($this->homeA['user']);
        $this->client->request('DELETE', $this->url('/api/points/bonuses/' . $foreign, $this->homeA));
        $this->assertResponseStatusCodeSame(404);
        $this->client->request('GET', $this->url('/api/points/day', $this->homeB) . '&date=' . $this->week);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testEachHomeClosesTheSameWeekAndOnlyPaysItsOwnAmount(): void
    {
        $this->earn($this->homeA, 10);
        $this->earn($this->homeB, 40);
        foreach ([[$this->homeA, 100], [$this->homeB, 400]] as [$home, $expected]) {
            $this->loginAs($home['user']);
            $this->assertSame(200, $this->putJson('/api/allowance/rules', ['teamId' => $home['teamId'], 'pointsAccount' => 'tasks', 'minimumPoints' => 0, 'rateAmount' => 10, 'ratePerPoints' => 1])->getStatusCode());
            $closed = $this->assertJsonResponse($this->postJson('/api/allowance/weeks/close', $this->weekPayload($home)), 200);
            $this->assertSame($expected, $closed['closure']['total']);
            $this->assertSame(400, $this->postJson('/api/allowance/weeks/close', $this->weekPayload($home))->getStatusCode());
            $this->assertSame($expected, $this->getJson($this->url('/api/allowance/wallet', $home))['pending']);
        }
        $this->loginAs($this->homeA['user']);
        $this->assertSame(400, $this->postJson('/api/allowance/payouts', ['teamId' => $this->homeA['teamId'], 'userId' => $this->child->id()->value(), 'amount' => 101])->getStatusCode());
        $this->assertSame(201, $this->postJson('/api/allowance/payouts', ['teamId' => $this->homeA['teamId'], 'userId' => $this->child->id()->value(), 'amount' => 100])->getStatusCode());
        $payout = $this->getJson($this->url('/api/allowance/payouts', $this->homeA))['payouts'][0]['id'];
        $this->loginAs($this->homeB['user']);
        $this->assertSame([], $this->getJson($this->url('/api/allowance/payouts', $this->homeB))['payouts']);
        $this->assertSame(403, $this->postJson('/api/allowance/payouts/' . $payout . '/cancel', [])->getStatusCode());
        $this->loginAs($this->child);
        $confirmed = $this->assertJsonResponse($this->postJson('/api/allowance/payouts/' . $payout . '/confirm', []), 200);
        $this->assertSame(100, $confirmed['available']);
        $this->assertSame(0, $confirmed['pending']);
        $other = $this->getJson('/api/allowance/wallet?teamId=' . $this->homeB['teamId']);
        $this->assertSame(400, $other['pending']);
        $this->assertSame(0, $other['available']);
        $this->loginAs($this->homeB['user']);
        $this->assertSame(200, $this->postJson('/api/allowance/weeks/reopen', $this->weekPayload($this->homeB))->getStatusCode());
        $this->loginAs($this->homeA['user']);
        $this->assertSame(100, $this->getJson($this->url('/api/allowance/weeks', $this->homeA) . '&weekStart=' . $this->week)['closure']['total']);
    }

    public function testSavingsAndSpendingCannotUseMoneyFromAnotherHome(): void
    {
        $this->loginAs($this->child);
        foreach ([[$this->homeA, 1000], [$this->homeB, 2000]] as [$home, $amount]) {
            $this->assertSame(201, $this->postJson('/api/allowance/income', ['teamId' => $home['teamId'], 'amount' => $amount, 'description' => 'Gift'])->getStatusCode());
        }
        $goal = $this->assertJsonResponse($this->postJson('/api/allowance/goals', ['teamId' => $this->homeA['teamId'], 'name' => 'Bicycle', 'target' => 5000]), 201)['goals'][0]['id'];
        $this->assertSame(400, $this->postJson('/api/allowance/goals/' . $goal . '/put-aside', ['amount' => 1001])->getStatusCode());
        $this->assertSame(200, $this->postJson('/api/allowance/goals/' . $goal . '/put-aside', ['amount' => 500])->getStatusCode());
        $this->assertSame([], $this->getJson('/api/allowance/goals?teamId=' . $this->homeB['teamId'])['goals']);
        $this->assertCount(1, $this->getJson('/api/allowance/ledger?teamId=' . $this->homeB['teamId'])['bookings']);
        $this->assertSame(400, $this->postJson('/api/allowance/expenses', ['teamId' => $this->homeA['teamId'], 'amount' => 501, 'description' => 'Too much'])->getStatusCode());
        $this->assertSame(2000, $this->getJson('/api/allowance/wallet?teamId=' . $this->homeB['teamId'])['available']);
    }

    public function testAmbiguousSelectionAndRemovedMembershipAreRejected(): void
    {
        $this->loginAs($this->child);
        foreach (['/api/points/week', '/api/allowance/wallet', '/api/allowance/weeks', '/api/allowance/goals', '/api/allowance/ledger'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseStatusCodeSame(400);
        }
        $this->assertSame(400, $this->postJson('/api/allowance/income', ['amount' => 100, 'description' => 'Ambiguous'])->getStatusCode());
        static::getContainer()->get(TeamMembershipRepositoryInterface::class)->leave(Uuid::fromString($this->homeA['teamId']), $this->child->id());
        $this->client->request('GET', '/api/allowance/wallet?teamId=' . $this->homeA['teamId']);
        $this->assertResponseStatusCodeSame(403);
        $this->getJson('/api/allowance/wallet');
    }

    public function testBonusConditionsCountOnlyTheRulesHousehold(): void
    {
        $this->week = (new DateTimeImmutable('today'))->format('Y-m-d');
        $this->earn($this->homeA, 10);
        $this->earn($this->homeB, 40);
        $evaluator = static::getContainer()->get(\App\TaskManagement\Domain\Service\BonusPointsEvaluator::class);
        foreach ([$this->homeA, $this->homeB] as $home) {
            $rule = \App\TaskManagement\Domain\Entity\BonusPointsRule::create(Uuid::generate(), Uuid::fromString($home['teamId']), 'Monthly', '', Points::fromInt(5), \App\TaskManagement\Domain\ValueObject\RuleConfig::monthlyTaskCount(2));
            $this->assertFalse($evaluator->isRuleMet($rule, $this->child->id()));
        }
        $rule = \App\TaskManagement\Domain\Entity\BonusPointsRule::create(Uuid::generate(), Uuid::fromString($this->homeA['teamId']), 'Weekly', '', Points::fromInt(5), \App\TaskManagement\Domain\ValueObject\RuleConfig::weeklyPointsSum(20, ['tasks']));
        $this->assertFalse($evaluator->isRuleMet($rule, $this->child->id()));
        $this->earn($this->homeA, 10);
        $this->assertTrue($evaluator->isRuleMet($rule, $this->child->id()));
    }

    public function testClosingOneHomeDoesNotLockTaskBookingInTheOther(): void
    {
        $a = $this->earn($this->homeA, 10);
        $b = $this->earn($this->homeB, 40);
        $day = (new DateTimeImmutable('sunday last week'))->format('Y-m-d');
        $this->loginAs($this->homeA['user']);
        $this->assertSame(200, $this->postJson('/api/allowance/weeks/close', $this->weekPayload($this->homeA))->getStatusCode());
        $this->assertSame(400, $this->postJson('/api/task-templates/' . $a->id()->value() . '/book', ['userId' => $this->child->id()->value(), 'doneOn' => $day])->getStatusCode());
        $this->loginAs($this->homeB['user']);
        $this->assertSame(201, $this->postJson('/api/task-templates/' . $b->id()->value() . '/book', ['userId' => $this->child->id()->value(), 'doneOn' => $day])->getStatusCode());
        $this->assertSame(80, $this->getJson('/api/users/' . $this->child->id()->value() . '/points?teamId=' . $this->homeB['teamId'])['balance']);
    }

    private function home(): array
    {
        $parent = User::create(Uuid::generate(), 'Parent', Email::fromString(Uuid::generate()->value() . '@example.com'), password_hash('password123', PASSWORD_BCRYPT), Role::USER);
        static::getContainer()->get(UserRepositoryInterface::class)->save($parent);
        $team = Uuid::generate();
        static::getContainer()->get('command.bus')->dispatch(new \App\TeamManagement\Application\Command\CreateTeamCommand($team->value(), 'Home', null, $parent->id()->value()));
        return ['teamId' => $team->value(), 'user' => $parent];
    }

    private function url(string $path, array $home): string
    {
        return $path . '?teamId=' . $home['teamId'] . '&userId=' . $this->child->id()->value();
    }

    private function weekPayload(array $home): array
    {
        return ['teamId' => $home['teamId'], 'userId' => $this->child->id()->value(), 'weekStart' => $this->week];
    }

    private function bonus(array $home, int $points): void
    {
        static::getContainer()->get(PointsLedger::class)->post($this->child->id(), AccountKind::BONUSES, $points, EntrySource::BONUS_RULE, 'Bonus', Uuid::generate(), 'week', new DateTimeImmutable($this->week), Uuid::fromString($home['teamId']));
    }

    private function earn(array $home, int $points): TaskTemplate
    {
        $team = Uuid::fromString($home['teamId']);
        $on = new DateTimeImmutable($this->week);
        $template = TaskTemplate::create(Uuid::generate(), TaskName::fromString('Task'), '', Points::fromInt($points), Frequency::fromString('daily'), ScheduleConfig::daily(), teamId: $team);
        static::getContainer()->get(TaskTemplateRepositoryInterface::class)->save($template);
        $clock = new FixedClock($on);
        $execution = TaskExecution::takeFromTemplate(Uuid::generate(), $template->id(), $template->name(), '', $template->points(), $this->child->id(), $on);
        $execution->complete($this->child->id(), $clock);
        $execution->approve($home['user']->id(), $clock);
        static::getContainer()->get(TaskExecutionRepositoryInterface::class)->save($execution);
        static::getContainer()->get(PointsLedger::class)->post($this->child->id(), AccountKind::TASKS, $points, EntrySource::TASK_EXECUTION, 'Task', $execution->id(), 'execution', $on, $team);
        return $template;
    }
}
