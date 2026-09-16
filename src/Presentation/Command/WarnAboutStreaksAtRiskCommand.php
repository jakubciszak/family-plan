<?php

declare(strict_types=1);

namespace App\Presentation\Command;

use App\Notifications\Communication\Domain\ValueObject\NotificationEvent;
use App\Notifications\Communication\Service\NotificationOrchestrator;
use App\Shared\Domain\Clock\ClockInterface;
use App\Shared\Domain\ValueObject\Uuid;
use App\TaskManagement\Domain\Entity\BonusPointsRule;
use App\TaskManagement\Domain\Repository\BonusPointsRuleRepositoryInterface;
use App\TaskManagement\Domain\Service\StreakAtRisk;
use App\TaskManagement\Domain\Service\StreakDailyPoints;
use App\TaskManagement\Domain\ValueObject\RuleType;
use App\TeamManagement\Domain\ReadModel\TeamMembership;
use App\TeamManagement\Domain\Repository\TeamMembershipRepositoryInterface;
use App\TeamManagement\Domain\Repository\TeamRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:warn-about-streaks-at-risk',
    description: 'Tell everyone whose streak runs out today that they have not earned anything yet',
)]
class WarnAboutStreaksAtRiskCommand extends Command
{
    private const HISTORY_DAYS = 60;

    public function __construct(
        private readonly TeamRepositoryInterface $teams,
        private readonly TeamMembershipRepositoryInterface $memberships,
        private readonly BonusPointsRuleRepositoryInterface $rules,
        private readonly StreakDailyPoints $streakPoints,
        private readonly NotificationOrchestrator $notifications,
        private readonly ClockInterface $clock
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'List who would be warned without sending anything'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $today = $this->clock->now()->setTime(0, 0);
        $warned = 0;

        foreach ($this->teams->findAll() as $team) {
            $rule = $this->streakRuleOf($team->id());

            if ($rule === null) {
                continue;
            }

            $pointsPerDay = $rule->config()->pointsPerDay() ?? 1;

            foreach ($this->memberships->ofTeam($team->id()) as $membership) {
                /** @var TeamMembership $membership */
                $days = $this->daysAtRisk($rule, $membership->userId(), $pointsPerDay, $today);

                if ($days === 0) {
                    continue;
                }

                $io->writeln(sprintf(
                    '%s: %d %s serii',
                    $membership->userId()->value(),
                    $days,
                    $days === 1 ? 'dzień' : 'dni'
                ));
                $warned++;

                if ($dryRun) {
                    continue;
                }

                $this->warn($membership->userId(), $rule, $days);
            }
        }

        $io->success(sprintf(
            $dryRun ? 'Ostrzeżenie dostałoby %d osób.' : 'Ostrzeżenia poszły do %d osób.',
            $warned
        ));

        return Command::SUCCESS;
    }

    private function daysAtRisk(
        BonusPointsRule $rule,
        Uuid $userId,
        int $pointsPerDay,
        DateTimeImmutable $today
    ): int {
        $counted = $this->streakPoints->perDay(
            $rule->config(),
            $userId,
            $today->modify(sprintf('-%d days', self::HISTORY_DAYS)),
            $today->modify('+1 day')
        );

        return StreakAtRisk::days($counted, $pointsPerDay, $today);
    }

    private function warn(Uuid $userId, BonusPointsRule $rule, int $days): void
    {
        $this->notifications->notifyUser(
            NotificationEvent::streakAtRisk(),
            $userId,
            sprintf(
                'Masz %d %s serii i dziś jeszcze nic nie zrobione. Jedno zadanie i seria zostaje.',
                $days,
                $days === 1 ? 'dzień' : 'dni'
            ),
            'Seria zaraz przepadnie',
            [
                'streak_days' => $days,
                'rule' => $rule->name(),
                'url' => '/tasks',
                'tag' => 'streak-at-risk',
            ]
        );
    }

    private function streakRuleOf(Uuid $teamId): ?BonusPointsRule
    {
        foreach ($this->rules->findActiveByTeamId($teamId) as $rule) {
            if ($rule->config()->type() === RuleType::CONSECUTIVE_DAYS) {
                return $rule;
            }
        }

        return null;
    }
}
