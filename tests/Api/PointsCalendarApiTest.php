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
use App\TeamManagement\Application\Command\CreateTeamCommand;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

class PointsCalendarApiTest extends ApiTestCase
{
    public function testTheWeekShowsPointsEarnedOnEachDay(): void
    {
        $monday = $this->mondayOfThisWeek();
        $this->earned(30, $monday);
        $this->earned(15, $monday->modify('+2 days'));

        $week = $this->getJson('/api/points/week');

        $this->assertSame($monday->format('Y-m-d'), $week['weekStart']);
        $this->assertCount(7, $week['days']);
        $this->assertSame(45, $week['total']);
        $this->assertSame(30, $week['days'][0]['points']);
        $this->assertSame(0, $week['days'][1]['points']);
        $this->assertSame(15, $week['days'][2]['points']);
    }

    public function testAWeekWithoutTasksIsAllZeros(): void
    {
        $week = $this->getJson('/api/points/week');

        $this->assertSame(0, $week['total']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], array_column($week['days'], 'points'));
        $this->assertNull($week['streak']);
    }

    public function testTodayIsMarked(): void
    {
        $week = $this->getJson('/api/points/week');

        $today = array_values(array_filter($week['days'], fn ($day) => $day['isToday']));

        $this->assertCount(1, $today);
        $this->assertSame((new DateTimeImmutable())->format('Y-m-d'), $today[0]['date']);
    }

    public function testAnotherWeekCanBeAskedFor(): void
    {
        $lastMonday = $this->mondayOfThisWeek()->modify('-7 days');
        $this->earned(20, $lastMonday);

        $week = $this->getJson('/api/points/week?weekStart=' . $lastMonday->format('Y-m-d'));

        $this->assertSame($lastMonday->format('Y-m-d'), $week['weekStart']);
        $this->assertSame(20, $week['days'][0]['points']);
    }

    public function testWithAStreakRuleTheDaysThatCountAreMarked(): void
    {
        $teamId = $this->teamOfCurrentUser();
        $this->streakRule($teamId, requiredDays: 3, pointsPerDay: 20);

        $monday = $this->mondayOfThisWeek();
        $this->earned(25, $monday);
        $this->earned(5, $monday->modify('+1 day'));
        $this->earned(30, $monday->modify('+2 days'));

        $week = $this->getJson('/api/points/week');

        $this->assertSame(3, $week['streak']['requiredDays']);
        $this->assertSame(20, $week['streak']['pointsPerDay']);
        $this->assertSame([true, false, true, false, false, false, false], array_column($week['days'], 'reachedThreshold'));
    }

    public function testDaysInARowFormTheCurrentStreak(): void
    {
        $teamId = $this->teamOfCurrentUser();
        $this->streakRule($teamId, requiredDays: 3, pointsPerDay: 20);

        $monday = $this->mondayOfThisWeek();
        $this->earned(25, $monday);
        $this->earned(25, $monday->modify('+1 day'));
        $this->earned(25, $monday->modify('+2 days'));

        $week = $this->getJson('/api/points/week');

        $this->assertSame(3, $week['streak']['length']);
        $this->assertTrue($week['streak']['met']);
        $this->assertSame([true, true, true, false, false, false, false], array_column($week['days'], 'inStreak'));
    }

    public function testADayBelowTheThresholdBreaksTheRun(): void
    {
        $teamId = $this->teamOfCurrentUser();
        $this->streakRule($teamId, requiredDays: 3, pointsPerDay: 20);

        $monday = $this->mondayOfThisWeek();
        $this->earned(25, $monday);
        $this->earned(5, $monday->modify('+1 day'));
        $this->earned(25, $monday->modify('+2 days'));

        $week = $this->getJson('/api/points/week');

        $this->assertSame(1, $week['streak']['length']);
        $this->assertFalse($week['streak']['met']);
    }

    private function mondayOfThisWeek(): DateTimeImmutable
    {
        return (new DateTimeImmutable())->modify('monday this week')->setTime(9, 0);
    }

    private function teamOfCurrentUser(): string
    {
        $teamId = Uuid::generate()->value();

        static::getContainer()->get('command.bus')->dispatch(
            new CreateTeamCommand($teamId, 'Rodzina', null, $this->currentUser->id()->value())
        );

        return $teamId;
    }

    private function streakRule(string $teamId, int $requiredDays, int $pointsPerDay): void
    {
        $this->assertJsonResponse(
            $this->postJson('/api/bonus-rules', [
                'teamId' => $teamId,
                'name' => 'Seria dni',
                'description' => 'opis',
                'bonusPoints' => 50,
                'ruleType' => 'consecutive_days',
                'ruleConfig' => ['requiredDays' => $requiredDays, 'pointsPerDay' => $pointsPerDay],
            ]),
            Response::HTTP_CREATED
        );
    }

    private function earned(int $points, DateTimeImmutable $on): void
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
            $this->currentUser->id(),
            $on
        );
        $execution->complete($this->currentUser->id(), $clock);
        $execution->approve($this->currentUser->id(), $clock);

        static::getContainer()->get(TaskExecutionRepositoryInterface::class)->save($execution);
    }
}
