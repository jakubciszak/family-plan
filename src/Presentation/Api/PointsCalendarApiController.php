<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Party\Application\Service\PartyResponsibilities;
use App\Party\Domain\ValueObject\ResponsibilityType;
use App\PointsManagement\Domain\Service\PointsLedger;
use App\PointsManagement\Domain\ValueObject\AccountKind;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Repository\BonusPointsRuleRepositoryInterface;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Service\DailyPoints;
use App\TaskManagement\Domain\Service\PointsStreak;
use App\TaskManagement\Domain\Service\StreakDailyPoints;
use App\TaskManagement\Domain\ValueObject\RuleType;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use DateTimeImmutable;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/points', name: 'api_points_')]
#[OA\Tag(name: 'Points')]
#[IsGranted('ROLE_USER')]
class PointsCalendarApiController extends AbstractController
{
    public function __construct(
        private readonly TaskExecutionRepositoryInterface $executionRepository,
        private readonly BonusPointsRuleRepositoryInterface $ruleRepository,
        private readonly PartyResponsibilities $responsibilities,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TeamMembershipRepositoryInterface $memberships,
        private readonly PointsLedger $ledger,
        private readonly StreakDailyPoints $streakPoints
    ) {
    }

    #[Route('/accounts', name: 'accounts', methods: ['GET'])]
    #[OA\Get(path: '/api/points/accounts', summary: 'Account kinds a bonus rule condition can count', tags: ['Points'])]
    #[OA\Response(response: 200, description: 'Known account kinds')]
    public function accounts(): JsonResponse
    {
        return $this->json([
            'accounts' => array_map(
                static fn (AccountKind $kind) => ['kind' => $kind->value],
                AccountKind::all()
            ),
        ]);
    }

    #[Route('/leaderboard', name: 'leaderboard', methods: ['GET'])]
    #[OA\Get(path: '/api/points/leaderboard', summary: 'Points every team member earned on each day of a week', tags: ['Points'])]
    #[OA\Parameter(name: 'teamId', in: 'query', required: true, description: 'Team to rank')]
    #[OA\Parameter(name: 'weekStart', in: 'query', required: false, description: 'Any day of the wanted week (Y-m-d)')]
    #[OA\Response(response: 200, description: 'Members ranked by the points they booked that week')]
    #[OA\Response(response: 403, description: 'Caller is not a member of the team')]
    public function leaderboard(Request $request): JsonResponse
    {
        $teamId = Uuid::fromString((string) $request->query->get('teamId'));

        if (!$this->memberships->isMember($this->callerId(), $teamId)) {
            throw $this->createAccessDeniedException('Only members see the standings of their team');
        }

        $monday = $this->mondayOf($request->query->get('weekStart'));
        $nextMonday = $monday->modify('+7 days');

        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $days[] = $monday->modify(sprintf('+%d days', $offset))->format('Y-m-d');
        }

        $standings = [];
        foreach ($this->memberships->ofTeam($teamId) as $membership) {
            if ($membership->isAdmin()) {
                continue;
            }

            $user = $this->userRepository->findById($membership->userId());

            if ($user === null) {
                continue;
            }

            $perDay = $this->earnedPerDay($membership->userId(), $monday, $nextMonday);

            $standings[] = [
                'userId' => $membership->userId()->value(),
                'name' => $user->name(),
                'total' => array_sum($perDay),
                'perDay' => array_map(static fn (string $day) => $perDay[$day] ?? 0, array_combine($days, $days)),
            ];
        }

        usort(
            $standings,
            static fn (array $a, array $b) => [$b['total'], $a['name']] <=> [$a['total'], $b['name']]
        );

        return $this->json([
            'weekStart' => $monday->format('Y-m-d'),
            'days' => $days,
            'today' => (new DateTimeImmutable())->format('Y-m-d'),
            'standings' => $standings,
        ]);
    }

    #[Route('/week', name: 'week', methods: ['GET'])]
    #[OA\Get(path: '/api/points/week', summary: 'Points earned on each day of a week, with the streak so far', tags: ['Points'])]
    #[OA\Parameter(name: 'weekStart', in: 'query', required: false, description: 'Any day of the wanted week (Y-m-d)')]
    #[OA\Parameter(name: 'userId', in: 'query', required: false, description: 'Member to look at; defaults to the caller')]
    #[OA\Response(response: 200, description: 'Seven days with points and streak marks')]
    public function week(Request $request): JsonResponse
    {
        $userId = $this->inspected($request);
        $monday = $this->mondayOf($request->query->get('weekStart'));
        $rule = $this->streakRule($userId);
        $pointsPerDay = $rule?->config()->pointsPerDay() ?? 1;

        $since = $monday->modify(sprintf('-%d days', $this->reach($rule)));

        $earned = $this->executionRepository->findApprovedByUserSince(
            $userId,
            $since,
            $rule?->config()->taskTemplateId()
        );

        $perDay = DailyPoints::perDay($earned);
        $bonusPerDay = $this->ledger->perDayBetween(
            $userId,
            $monday,
            $monday->modify('+7 days'),
            [AccountKind::BONUSES]
        );
        $counted = $rule === null
            ? []
            : $this->streakPoints->perDay($rule->config(), $userId, $since, $monday->modify('+7 days'));
        $today = (new DateTimeImmutable())->format('Y-m-d');
        $lastDay = min($today, $monday->modify('+6 days')->format('Y-m-d'));
        $streakDays = $rule === null ? [] : PointsStreak::aliveOn($counted, $lastDay, $pointsPerDay);

        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $day = $monday->modify(sprintf('+%d days', $offset))->format('Y-m-d');
            $points = $perDay[$day] ?? 0;

            $days[] = [
                'date' => $day,
                'points' => $points,
                'bonus' => $bonusPerDay[$day] ?? 0,
                'reachedThreshold' => $rule !== null && ($counted[$day] ?? 0) >= $pointsPerDay,
                'inStreak' => in_array($day, $streakDays, true),
                'isToday' => $day === $today,
            ];
        }

        return $this->json([
            'weekStart' => $monday->format('Y-m-d'),
            'total' => array_sum(array_column($days, 'points')),
            'bonusTotal' => array_sum(array_column($days, 'bonus')),
            'days' => $days,
            'streak' => $rule === null ? null : [
                'name' => $rule->name(),
                'requiredDays' => $rule->config()->requiredDays(),
                'pointsPerDay' => $pointsPerDay,
                'bonusPoints' => $rule->bonusPoints()->value(),
                'length' => count($streakDays),
                'met' => count($streakDays) >= $rule->config()->requiredDays(),
            ],
        ]);
    }

    #[Route('/day', name: 'day', methods: ['GET'])]
    #[OA\Get(path: '/api/points/day', summary: 'What a member did on one day, and what it earned', tags: ['Points'])]
    #[OA\Parameter(name: 'date', in: 'query', required: true, description: 'The day to open (Y-m-d)')]
    #[OA\Parameter(name: 'userId', in: 'query', required: false, description: 'Member to look at; defaults to the caller')]
    #[OA\Response(response: 200, description: 'Tasks approved that day, with their points')]
    #[OA\Response(response: 403, description: 'Caller may not look at this member')]
    public function day(Request $request): JsonResponse
    {
        $userId = $this->inspected($request);
        $day = DailyPoints::day((string) $request->query->get('date'));
        $next = $day->modify('+1 day');

        $done = array_values(array_filter(
            $this->executionRepository->findApprovedByUserSince($userId, $day),
            static fn ($execution) => $execution->earnedOn() >= $day && $execution->earnedOn() < $next
        ));

        usort($done, static fn ($a, $b) => $a->earnedOn() <=> $b->earnedOn());

        $bonuses = $this->ledger->between($userId, $day, $next, [AccountKind::BONUSES]);

        return $this->json([
            'date' => $day->format('Y-m-d'),
            'userId' => $userId->value(),
            'tasks' => array_map(static fn ($execution) => [
                'id' => $execution->id()->value(),
                'name' => $execution->name()?->value(),
                'points' => $execution->points()?->value() ?? 0,
                'earnedOn' => $execution->earnedOn()->format('c'),
            ], $done),
            'total' => array_sum(array_map(static fn ($e) => $e->points()?->value() ?? 0, $done)),
            'bonus' => array_sum(array_map(static fn ($entry) => $entry->amount(), $bonuses)),
            'bonuses' => array_map(static fn ($entry) => [
                'points' => $entry->amount(),
                'name' => $entry->description(),
                'ruleId' => $entry->reference(),
            ], $bonuses),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function earnedPerDay(Uuid $userId, DateTimeImmutable $monday, DateTimeImmutable $nextMonday): array
    {
        $perDay = array_filter(
            DailyPoints::perDay($this->executionRepository->findApprovedByUserSince($userId, $monday)),
            static fn (string $day) => $day < $nextMonday->format('Y-m-d'),
            ARRAY_FILTER_USE_KEY
        );

        foreach ($this->ledger->perDayBetween($userId, $monday, $nextMonday, [AccountKind::BONUSES]) as $day => $bonus) {
            $perDay[$day] = ($perDay[$day] ?? 0) + $bonus;
        }

        return $perDay;
    }

    private function inspected(Request $request): Uuid
    {
        $wanted = $request->query->get('userId');
        $caller = $this->callerId();

        if ($wanted === null || $wanted === $caller->value()) {
            return $caller;
        }

        $target = Uuid::fromString((string) $wanted);

        foreach ($this->memberships->ofUser($target) as $membership) {
            if ($this->memberships->isAdmin($caller, $membership->teamId())) {
                return $target;
            }
        }

        throw $this->createAccessDeniedException('Only an admin of their team looks at another member');
    }

    private function reach(?BonusPointsRule $rule): int
    {
        return max(7, ($rule?->config()->requiredDays() ?? 0) * 2);
    }

    private function mondayOf(?string $day): DateTimeImmutable
    {
        $within = $day === null ? new DateTimeImmutable() : new DateTimeImmutable($day);

        return $within->modify('monday this week')->setTime(0, 0);
    }

    private function streakRule(Uuid $userId): ?BonusPointsRule
    {
        foreach ($this->responsibilities->organizationsWhereMay($userId, ResponsibilityType::takeTask()) as $teamId) {
            foreach ($this->ruleRepository->findActiveByTeamId(Uuid::fromString($teamId)) as $rule) {
                if ($rule->config()->type() === RuleType::CONSECUTIVE_DAYS) {
                    return $rule;
                }
            }
        }

        return null;
    }

    private function callerId(): Uuid
    {
        return $this->userRepository
            ->findByEmail(Email::fromString($this->getUser()->getUserIdentifier()))
            ->id();
    }
}
