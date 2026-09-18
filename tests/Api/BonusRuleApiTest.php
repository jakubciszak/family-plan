<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\HttpFoundation\Response;

class BonusRuleApiTest extends ApiTestCase
{
    public function testTeamAdminCreatesARuleForTheirTeam(): void
    {
        $teamId = $this->createTeam();

        $response = $this->postJson('/api/bonus-rules', [
            'teamId' => $teamId,
            'name' => 'Tydzien bez marudzenia',
            'description' => 'Piec dni z rzedu',
            'bonusPoints' => 50,
            'ruleType' => 'monthly_task_count',
            'ruleConfig' => ['requiredCount' => 5],
        ]);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), $response->getContent());

        $rules = $this->getJson('/api/bonus-rules')['rules'];
        $this->assertContains('Tydzien bez marudzenia', array_column($rules, 'name'));
    }

    public function testRuleForConsecutiveDaysNeedsAnExistingTemplate(): void
    {
        $teamId = $this->createTeam();

        $response = $this->postJson('/api/bonus-rules', [
            'teamId' => $teamId,
            'name' => 'Piec dni z rzedu',
            'description' => 'Seria',
            'bonusPoints' => 30,
            'ruleType' => 'consecutive_days',
            'ruleConfig' => ['taskTemplateId' => 'nie-jest-uuid', 'requiredDays' => 5],
        ]);

        $this->assertNotSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $response->getStatusCode(),
            'Niepoprawny identyfikator szablonu musi dac blad walidacji, nie 500.'
        );
    }

    public function testAStreakRuleKeepsTheAccountsItWasGiven(): void
    {
        $teamId = $this->createTeam();

        $this->assertSame(
            Response::HTTP_CREATED,
            $this->postJson('/api/bonus-rules', [
                'teamId' => $teamId,
                'name' => 'Seria za bonusy',
                'description' => 'Trzy dni z rzedu',
                'bonusPoints' => 30,
                'ruleType' => 'consecutive_days',
                'ruleConfig' => [
                    'requiredDays' => 3,
                    'pointsPerDay' => 20,
                    'accounts' => ['bonuses'],
                ],
            ])->getStatusCode()
        );

        $rules = $this->getJson('/api/bonus-rules')['rules'];
        $rule = array_values(array_filter($rules, static fn (array $rule) => $rule['name'] === 'Seria za bonusy'))[0];

        $this->assertSame(['bonuses'], $rule['config']['accounts']);
    }

    public function testAStreakRuleWithoutAccountsCountsTaskPoints(): void
    {
        $teamId = $this->createTeam();

        $this->postJson('/api/bonus-rules', [
            'teamId' => $teamId,
            'name' => 'Seria domyslna',
            'description' => 'Trzy dni z rzedu',
            'bonusPoints' => 30,
            'ruleType' => 'consecutive_days',
            'ruleConfig' => ['requiredDays' => 3, 'pointsPerDay' => 20],
        ]);

        $rules = $this->getJson('/api/bonus-rules')['rules'];
        $rule = array_values(array_filter($rules, static fn (array $rule) => $rule['name'] === 'Seria domyslna'))[0];

        $this->assertSame(['tasks'], $rule['config']['accounts']);
    }

    public function testAStreakRuleRefusesAnUnknownAccount(): void
    {
        $teamId = $this->createTeam();

        $response = $this->postJson('/api/bonus-rules', [
            'teamId' => $teamId,
            'name' => 'Seria z kosmosu',
            'description' => 'Trzy dni z rzedu',
            'bonusPoints' => 30,
            'ruleType' => 'consecutive_days',
            'ruleConfig' => ['requiredDays' => 3, 'accounts' => ['skarbonka']],
        ]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRulesAreScopedToTheCallersTeams(): void
    {
        $ownTeam = $this->createTeam('Moja rodzina');
        $this->postJson('/api/bonus-rules', [
            'teamId' => $ownTeam,
            'name' => 'Moja regula',
            'description' => 'opis',
            'bonusPoints' => 10,
            'ruleType' => 'monthly_task_count',
            'ruleConfig' => ['requiredCount' => 3],
        ]);

        $this->loginAs($this->authenticate(Role::USER));

        $rules = $this->getJson('/api/bonus-rules')['rules'];
        $this->assertNotContains('Moja regula', array_column($rules, 'name'));
    }

    public function testRuleCannotBeCreatedForSomebodyElsesTeam(): void
    {
        $foreignTeam = $this->createTeam('Obca rodzina');

        $this->loginAs($this->authenticate(Role::USER));

        $response = $this->postJson('/api/bonus-rules', [
            'teamId' => $foreignTeam,
            'name' => 'Podszywka',
            'description' => 'opis',
            'bonusPoints' => 10,
            'ruleType' => 'monthly_task_count',
            'ruleConfig' => ['requiredCount' => 3],
        ]);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testCreatingARuleWithABrokenConditionIsRefused(): void
    {
        $teamId = $this->createTeam();

        $response = $this->postJson('/api/bonus-rules', [
            'teamId' => $teamId,
            'name' => 'Seria',
            'description' => 'Jeden dzien',
            'bonusPoints' => 30,
            'ruleType' => 'consecutive_days',
            'ruleConfig' => ['requiredDays' => 1, 'pointsPerDay' => 10],
        ]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), $response->getContent());
    }

    public function testEditingARuleCanRetuneItsCondition(): void
    {
        $teamId = $this->createTeam();

        $this->assertJsonResponse(
            $this->postJson('/api/bonus-rules', [
                'teamId' => $teamId,
                'name' => 'Seria',
                'description' => 'Piec dni z rzedu',
                'bonusPoints' => 30,
                'ruleType' => 'consecutive_days',
                'ruleConfig' => ['requiredDays' => 5, 'pointsPerDay' => 10, 'accounts' => ['tasks']],
            ]),
            Response::HTTP_CREATED
        );

        $rule = $this->getJson('/api/bonus-rules')['rules'][0];

        $this->assertJsonResponse(
            $this->putJson('/api/bonus-rules/' . $rule['id'], [
                'name' => 'Seria',
                'description' => 'Trzy dni z rzedu, bonusy tez licza',
                'bonusPoints' => 30,
                'ruleType' => 'consecutive_days',
                'ruleConfig' => [
                    'requiredDays' => 3,
                    'pointsPerDay' => 4,
                    'accounts' => ['tasks', 'bonuses'],
                ],
            ]),
            Response::HTTP_OK
        );

        $changed = $this->getJson('/api/bonus-rules')['rules'][0];
        $this->assertSame(3, $changed['config']['requiredDays']);
        $this->assertSame(4, $changed['config']['pointsPerDay']);
        $this->assertSame(['tasks', 'bonuses'], $changed['config']['accounts']);
    }

    public function testEditingARuleCanSwapItsType(): void
    {
        $teamId = $this->createTeam();

        $this->assertJsonResponse(
            $this->postJson('/api/bonus-rules', [
                'teamId' => $teamId,
                'name' => 'Miesiac',
                'description' => 'Dwadziescia zadan',
                'bonusPoints' => 40,
                'ruleType' => 'monthly_task_count',
                'ruleConfig' => ['requiredCount' => 20],
            ]),
            Response::HTTP_CREATED
        );

        $rule = $this->getJson('/api/bonus-rules')['rules'][0];

        $this->assertJsonResponse(
            $this->putJson('/api/bonus-rules/' . $rule['id'], [
                'name' => 'Tydzien',
                'description' => 'Sto punktow w tygodniu',
                'bonusPoints' => 40,
                'ruleType' => 'weekly_points_sum',
                'ruleConfig' => ['requiredPoints' => 100, 'accounts' => ['tasks']],
            ]),
            Response::HTTP_OK
        );

        $changed = $this->getJson('/api/bonus-rules')['rules'][0];
        $this->assertSame('weekly_points_sum', $changed['type']);
        $this->assertSame(100, $changed['config']['requiredPoints']);
    }

    public function testEditingARuleIntoABrokenConditionIsRefused(): void
    {
        $teamId = $this->createTeam();

        $this->assertJsonResponse(
            $this->postJson('/api/bonus-rules', [
                'teamId' => $teamId,
                'name' => 'Seria',
                'description' => 'Piec dni z rzedu',
                'bonusPoints' => 30,
                'ruleType' => 'consecutive_days',
                'ruleConfig' => ['requiredDays' => 5, 'pointsPerDay' => 10],
            ]),
            Response::HTTP_CREATED
        );

        $rule = $this->getJson('/api/bonus-rules')['rules'][0];

        $response = $this->putJson('/api/bonus-rules/' . $rule['id'], [
            'name' => 'Seria',
            'description' => 'Jeden dzien',
            'bonusPoints' => 30,
            'ruleType' => 'consecutive_days',
            'ruleConfig' => ['requiredDays' => 1, 'pointsPerDay' => 10],
        ]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), $response->getContent());
    }

    private function createTeam(string $name = 'Rodzina'): string
    {
        $team = $this->assertJsonResponse(
            $this->postJson('/api/teams', ['name' => $name, 'description' => null]),
            Response::HTTP_CREATED
        );

        return $team['id'];
    }
}
