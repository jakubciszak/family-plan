<?php

declare(strict_types=1);

namespace App\SchoolTimetable\Infrastructure\Console;

use App\SchoolTimetable\Application\Service\SchoolAccounts;
use App\SchoolTimetable\Domain\Repository\SchoolAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Uuid;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(name: 'app:school-timetable:dump', description: 'Write the raw mobidziennik timetable pages of a team to disk')]
final class DumpTimetableCommand extends Command
{
    public function __construct(private readonly SchoolAccountRepositoryInterface $accounts, private readonly SchoolAccounts $configuration)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('teamId', InputArgument::REQUIRED);
        $this->addArgument('weekStart', InputArgument::REQUIRED);
        $this->addArgument('directory', InputArgument::OPTIONAL, '', '/tmp');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $account = $this->accounts->ofTeam(Uuid::fromString($input->getArgument('teamId')));
        if ($account === null) {
            $output->writeln('No account for this team.');

            return Command::FAILURE;
        }

        $credentials = $this->configuration->credentials($account);
        $browser = new HttpBrowser(HttpClient::create(['timeout' => 25, 'headers' => ['User-Agent' => 'Mozilla/5.0']]));
        $browser->request('POST', $credentials->url('/dziennik'), ['login' => $credentials->login, 'haslo' => $credentials->password]);

        foreach (['podstawowy', 'pozalekcyjny'] as $plan) {
            $browser->request('GET', $credentials->url(sprintf('/dziennik/planlekcji?typ=%s&tydzien=%s', $plan, $input->getArgument('weekStart'))));
            $target = sprintf('%s/plan-%s.html', rtrim($input->getArgument('directory'), '/'), $plan);
            file_put_contents($target, $browser->getResponse()->getContent());
            $output->writeln(sprintf('%s: %d bytes', $target, (int) filesize($target)));
        }

        return Command::SUCCESS;
    }
}
