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

    private function createTeam(string $name = 'Rodzina'): string
    {
        $team = $this->assertJsonResponse(
            $this->postJson('/api/teams', ['name' => $name, 'description' => null]),
            Response::HTTP_CREATED
        );

        return $team['id'];
    }
}
