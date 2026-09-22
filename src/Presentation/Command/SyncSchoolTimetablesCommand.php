<?php

declare(strict_types=1);

namespace App\Presentation\Command;

use App\SchoolTimetable\Application\Service\TimetableSync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:school-timetable:sync',
    description: 'Import the timetable of every team whose students already point at a family member',
)]
final class SyncSchoolTimetablesCommand extends Command
{
    public function __construct(private readonly TimetableSync $sync)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('weeks', null, InputOption::VALUE_REQUIRED, 'How many weeks from this one to import', '2');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new SymfonyStyle($input, $output);
        $report = $this->sync->run((int) $input->getOption('weeks'));

        if ($report === []) {
            $console->writeln('No school account configured.');

            return Command::SUCCESS;
        }

        $failed = false;
        foreach ($report as $team) {
            if (isset($team['skipped'])) {
                $console->writeln(sprintf('%s: skipped (%s)', $team['teamId'], $team['skipped']));
                continue;
            }

            foreach ($team['weeks'] ?? [] as $week => $counts) {
                $console->writeln(sprintf('%s %s: added %d, changed %d, removed %d, untouched %d', $team['teamId'], $week, $counts['added'], $counts['updated'], $counts['removed'], $counts['unchanged']));
            }

            if (isset($team['failed'])) {
                $failed = true;
                $console->writeln(sprintf('<error>%s: %s</error>', $team['teamId'], $team['failed']));
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
