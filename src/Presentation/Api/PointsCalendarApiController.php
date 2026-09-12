<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Party\Application\Service\PartyResponsibilities;
use App\Party\Domain\ValueObject\ResponsibilityType;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Repository\BonusPointsRuleRepositoryInterface;
use App\TaskManagement\Domain\Repository\TaskExecutionRepositoryInterface;
use App\TaskManagement\Domain\Service\DailyPoints;
use App\TaskManagement\Domain\Service\ExecutionStreak;
use App\TaskManagement\Domain\ValueObject\RuleType;
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
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    #[Route('/week', name: 'week', methods: ['GET'])]
    #[OA\Get(path: '/api/points/week', summary: 'Points earned on each day of a week, with the streak so far', tags: ['Points'])]
    #[OA\Parameter(name: 'weekStart', in: 'query', required: false, description: 'Any day of the wanted week (Y-m-d)')]
    #[OA\Response(response: 200, description: 'Seven days with points and streak marks')]
    public function week(Request $request): JsonResponse
    {
        $userId = $this->callerId();
        $monday = $this->mondayOf($request->query->get('weekStart'));
        $rule = $this->streakRule($userId);
        $pointsPerDay = $rule?->config()->pointsPerDay() ?? 1;

        $earned = $this->executionRepository->findApprovedByUserSince(
            $userId,
            $monday->modify(sprintf('-%d days', $this->reach($rule))),
            $rule?->config()->taskTemplateId()
        );

        $perDay = DailyPoints::perDay($earned);
        $streakDays = $rule === null ? [] : ExecutionStreak::current($earned, $pointsPerDay);
        $today = (new DateTimeImmutable())->format('Y-m-d');

        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $day = $monday->modify(sprintf('+%d days', $offset))->format('Y-m-d');
            $points = $perDay[$day] ?? 0;

            $days[] = [
                'date' => $day,
                'points' => $points,
                'reachedThreshold' => $rule !== null && $points >= $pointsPerDay,
                'inStreak' => in_array($day, $streakDays, true),
                'isToday' => $day === $today,
            ];
        }

        return $this->json([
            'weekStart' => $monday->format('Y-m-d'),
            'total' => array_sum(array_column($days, 'points')),
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
