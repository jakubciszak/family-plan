<?php

declare(strict_types=1);

namespace App\Tests\Api;

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
use Symfony\Component\HttpFoundation\Response;

class AllowanceApiTest extends ApiTestCase
{
    private string $teamId;

    private User $admin;

    private User $child;

    protected function setUp(): void
    {
        parent::setUp();

        $team = $this->createTeamAndAdmin();
        $this->teamId = $team['teamId'];
        $this->admin = $team['user'];
        $this->child = $this->memberOfTheTeam();
    }

    public function testAnAdminSetsWhatPointsAreWorthAndReadsItBack(): void
    {
        $this->assertSame(Response::HTTP_OK, $this->setRule('tasks', 50, 25, 1)->getStatusCode());

        $rules = $this->getJson('/api/allowance/rules?teamId=' . $this->teamId);

        $this->assertCount(1, $rules['rules']);
        $this->assertSame('tasks', $rules['rules'][0]['pointsAccount']);
        $this->assertSame(50, $rules['rules'][0]['minimumPoints']);
        $this->assertSame(25, $rules['rules'][0]['rateAmount']);
    }

    public function testSomeoneWhoDoesNotAdministerTheTeamCannotSetRules(): void
    {
        $this->loginAs($this->child);

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->setRule('tasks', 0, 25, 1)->getStatusCode());
    }

    public function testAWeekShowsWhatThePointsCollectedSoFarWouldPay(): void
    {
        $this->setRule('tasks', 50, 10, 1);
        $monday = $this->lastMonday();
        $this->earned($this->child, 60, $monday);

        $week = $this->week($monday);

        $this->assertSame(60, $week['points']);
        $this->assertSame(600, $week['expected']['total']);
        $this->assertNull($week['closure']);
    }

    public function testPointsUnderTheMinimumPayNothing(): void
    {
        $this->setRule('tasks', 50, 10, 1);
        $monday = $this->lastMonday();
        $this->earned($this->child, 40, $monday);

        $week = $this->week($monday);

        $this->assertSame(0, $week['expected']['total']);
        $this->assertFalse($week['expected']['lines'][0]['reachedMinimum']);
    }

    public function testClosingAWeekPutsTheMoneyOnTheWaitingAccount(): void
    {
        $this->setRule('tasks', 0, 10, 1);
        $monday = $this->lastMonday();
        $this->earned($this->child, 60, $monday);

        $closed = $this->assertJsonResponse($this->closeWeek($monday));

        $this->assertNotNull($closed['closure']);
        $this->assertSame(600, $closed['closure']['total']);

        $wallet = $this->getJson('/api/allowance/wallet?userId=' . $this->child->id()->value());

        $this->assertSame(600, $wallet['pending']);
        $this->assertSame(0, $wallet['available']);
    }

    public function testAWeekIsOnlyClosedOnce(): void
    {
        $this->setRule('tasks', 0, 10, 1);
        $monday = $this->lastMonday();
        $this->earned($this->child, 60, $monday);

        $this->closeWeek($monday);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->closeWeek($monday)->getStatusCode());
    }

    public function testOpeningAWeekAgainTakesTheMoneyBackOff(): void
    {
        $this->setRule('tasks', 0, 10, 1);
        $monday = $this->lastMonday();
        $this->earned($this->child, 60, $monday);
        $this->closeWeek($monday);

        $reopened = $this->assertJsonResponse($this->postJson('/api/allowance/weeks/reopen', [
            'userId' => $this->child->id()->value(),
            'weekStart' => $monday->format('Y-m-d'),
        ]));

        $this->assertNull($reopened['closure']);
        $this->assertSame(0, $this->getJson('/api/allowance/wallet?userId=' . $this->child->id()->value())['pending']);
    }

    public function testMoreCannotBePaidOutThanIsWaiting(): void
    {
        $this->earnAndClose(600);

        $response = $this->postJson('/api/allowance/payouts', [
            'userId' => $this->child->id()->value(),
            'amount' => 601,
        ]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testPartOfWhatIsWaitingCanBeHandedOverAndTheOwnerConfirmsIt(): void
    {
        $this->earnAndClose(600);

        $offered = $this->assertJsonResponse($this->postJson('/api/allowance/payouts', [
            'userId' => $this->child->id()->value(),
            'amount' => 400,
            'note' => 'Reszta w piatek',
        ]), Response::HTTP_CREATED);

        $this->assertCount(1, $offered['awaitingConfirmation']);
        $this->assertSame(600, $offered['pending']);

        $this->loginAs($this->child);
        $wallet = $this->assertJsonResponse(
            $this->postJson('/api/allowance/payouts/' . $offered['awaitingConfirmation'][0]['id'] . '/confirm', [])
        );

        $this->assertSame(200, $wallet['pending']);
        $this->assertSame(400, $wallet['available']);
        $this->assertSame([], $wallet['awaitingConfirmation']);
    }

    public function testTheSameMoneyCannotBeOfferedTwiceBeforeItIsConfirmed(): void
    {
        $this->earnAndClose(600);

        $this->postJson('/api/allowance/payouts', ['userId' => $this->child->id()->value(), 'amount' => 400]);

        $response = $this->postJson('/api/allowance/payouts', [
            'userId' => $this->child->id()->value(),
            'amount' => 400,
        ]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testNobodyCanSpendMoreThanTheyHaveInHand(): void
    {
        $this->loginAs($this->child);

        $response = $this->postJson('/api/allowance/expenses', ['amount' => 100, 'description' => 'Lody']);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testMoneyFromSomewhereElseCanBeBookedAndSpent(): void
    {
        $this->loginAs($this->child);

        $wallet = $this->assertJsonResponse(
            $this->postJson('/api/allowance/income', ['amount' => 5000, 'description' => 'Babcia']),
            Response::HTTP_CREATED
        );
        $this->assertSame(5000, $wallet['available']);

        $wallet = $this->assertJsonResponse(
            $this->postJson('/api/allowance/expenses', ['amount' => 1500, 'description' => 'Lody']),
            Response::HTTP_CREATED
        );

        $this->assertSame(3500, $wallet['available']);
        $this->assertSame(1500, $wallet['spent']);

        $ledger = $this->getJson('/api/allowance/ledger');

        $this->assertCount(2, $ledger['bookings']);
        $this->assertSame('expense', $ledger['bookings'][0]['type']);
    }

    public function testMoneyPutAsideForAGoalLeavesTheSpendableWallet(): void
    {
        $this->loginAs($this->child);
        $this->postJson('/api/allowance/income', ['amount' => 5000, 'description' => 'Babcia']);

        $goals = $this->assertJsonResponse(
            $this->postJson('/api/allowance/goals', ['name' => 'Hulajnoga', 'target' => 4000]),
            Response::HTTP_CREATED
        );
        $goalId = $goals['goals'][0]['id'];

        $goals = $this->assertJsonResponse($this->postJson('/api/allowance/goals/' . $goalId . '/put-aside', [
            'amount' => 4000,
        ]));

        $this->assertSame(4000, $goals['goals'][0]['saved']);
        $this->assertTrue($goals['goals'][0]['reached']);
        $this->assertSame(100, $goals['goals'][0]['percent']);

        $wallet = $this->getJson('/api/allowance/wallet');

        $this->assertSame(1000, $wallet['available']);
        $this->assertSame(4000, $wallet['putAside']);
    }

    public function testMoneyPutAsideCanBeTakenBack(): void
    {
        $this->loginAs($this->child);
        $this->postJson('/api/allowance/income', ['amount' => 5000, 'description' => 'Babcia']);
        $goalId = $this->assertJsonResponse(
            $this->postJson('/api/allowance/goals', ['name' => 'Hulajnoga', 'target' => 4000]),
            Response::HTTP_CREATED
        )['goals'][0]['id'];
        $this->postJson('/api/allowance/goals/' . $goalId . '/put-aside', ['amount' => 4000]);

        $goals = $this->assertJsonResponse($this->postJson('/api/allowance/goals/' . $goalId . '/take-back', [
            'amount' => 1000,
        ]));

        $this->assertSame(3000, $goals['goals'][0]['saved']);
        $this->assertFalse($goals['goals'][0]['reached']);
        $this->assertSame(2000, $this->getJson('/api/allowance/wallet')['available']);
    }

    public function testNobodyPutsAsideMoreThanTheyHave(): void
    {
        $this->loginAs($this->child);
        $goalId = $this->assertJsonResponse(
            $this->postJson('/api/allowance/goals', ['name' => 'Hulajnoga', 'target' => 4000]),
            Response::HTTP_CREATED
        )['goals'][0]['id'];

        $response = $this->postJson('/api/allowance/goals/' . $goalId . '/put-aside', ['amount' => 100]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testGivingUpOnAGoalReturnsWhatWasPutAside(): void
    {
        $this->loginAs($this->child);
        $this->postJson('/api/allowance/income', ['amount' => 5000, 'description' => 'Babcia']);
        $goalId = $this->assertJsonResponse(
            $this->postJson('/api/allowance/goals', ['name' => 'Hulajnoga', 'target' => 4000]),
            Response::HTTP_CREATED
        )['goals'][0]['id'];
        $this->postJson('/api/allowance/goals/' . $goalId . '/put-aside', ['amount' => 4000]);

        $goals = $this->assertJsonResponse($this->deleteJson('/api/allowance/goals/' . $goalId));

        $this->assertSame([], $goals['goals']);
        $this->assertSame(5000, $this->getJson('/api/allowance/wallet')['available']);
    }

    public function testNobodyTouchesSomeoneElsesGoals(): void
    {
        $this->loginAs($this->child);
        $goalId = $this->assertJsonResponse(
            $this->postJson('/api/allowance/goals', ['name' => 'Hulajnoga', 'target' => 4000]),
            Response::HTTP_CREATED
        )['goals'][0]['id'];

        $this->loginAs($this->admin);

        $this->assertSame(
            Response::HTTP_FORBIDDEN,
            $this->postJson('/api/allowance/goals/' . $goalId . '/put-aside', ['amount' => 100])->getStatusCode()
        );
    }

    public function testAnAdminSeesAMembersWalletButAStrangerDoesNot(): void
    {
        $this->earnAndClose(600);

        $this->assertSame(600, $this->getJson('/api/allowance/wallet?userId=' . $this->child->id()->value())['pending']);

        $this->loginAs($this->authenticate());
        $this->client->request('GET', '/api/allowance/wallet?userId=' . $this->child->id()->value());

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testAWeekCannotBeOpenedAgainOnceTheMoneyIsInTheirHands(): void
    {
        $this->earnAndClose(600);
        $offered = $this->assertJsonResponse(
            $this->postJson('/api/allowance/payouts', [
                'userId' => $this->child->id()->value(),
                'amount' => 600,
            ]),
            Response::HTTP_CREATED
        );

        $this->loginAs($this->child);
        $this->postJson('/api/allowance/payouts/' . $offered['awaitingConfirmation'][0]['id'] . '/confirm', []);
        $this->loginAs($this->admin);

        $response = $this->postJson('/api/allowance/weeks/reopen', [
            'userId' => $this->child->id()->value(),
            'weekStart' => $this->lastMonday()->format('Y-m-d'),
        ]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    private function earnAndClose(int $expected): void
    {
        $this->setRule('tasks', 0, 10, 1);
        $monday = $this->lastMonday();
        $this->earned($this->child, intdiv($expected, 10), $monday);
        $this->closeWeek($monday);
    }

    private function setRule(string $account, int $minimumPoints, int $rateAmount, int $ratePerPoints): Response
    {
        return $this->putJson('/api/allowance/rules', [
            'teamId' => $this->teamId,
            'pointsAccount' => $account,
            'minimumPoints' => $minimumPoints,
            'rateAmount' => $rateAmount,
            'ratePerPoints' => $ratePerPoints,
        ]);
    }

    private function closeWeek(DateTimeImmutable $monday): Response
    {
        return $this->postJson('/api/allowance/weeks/close', [
            'userId' => $this->child->id()->value(),
            'weekStart' => $monday->format('Y-m-d'),
        ]);
    }

    private function week(DateTimeImmutable $monday): array
    {
        return $this->getJson(sprintf(
            '/api/allowance/weeks?userId=%s&weekStart=%s',
            $this->child->id()->value(),
            $monday->format('Y-m-d')
        ));
    }

    private function lastMonday(): DateTimeImmutable
    {
        return (new DateTimeImmutable())->modify('monday this week')->modify('-7 days')->setTime(0, 0);
    }

    private function memberOfTheTeam(): User
    {
        $child = User::create(
            Uuid::generate(),
            'Dziecko',
            Email::fromString(sprintf('child-%s@example.com', uniqid())),
            password_hash('password123', PASSWORD_BCRYPT),
            Role::USER
        );

        static::getContainer()->get(UserRepositoryInterface::class)->save($child);
        static::getContainer()->get(TeamMembershipRepositoryInterface::class)->join(
            Uuid::fromString($this->teamId),
            $child->id(),
            TeamRole::member()
        );

        return $child;
    }

    private function earned(User $user, int $points, DateTimeImmutable $on): void
    {
        $template = TaskTemplate::create(
            Uuid::generate(),
            TaskName::fromString('Zmywanie'),
            'opis',
            Points::fromInt($points),
            Frequency::fromString('daily'),
            ScheduleConfig::daily()
        );
        static::getContainer()->get(TaskTemplateRepositoryInterface::class)->save($template);

        $clock = new FixedClock($on);
        $execution = TaskExecution::takeFromTemplate(
            Uuid::generate(),
            $template->id(),
            $template->name(),
            $template->description(),
            $template->points(),
            $user->id(),
            $on
        );
        $execution->complete($user->id(), $clock);
        $execution->approve($user->id(), $clock);

        static::getContainer()->get(TaskExecutionRepositoryInterface::class)->save($execution);
    }
}
