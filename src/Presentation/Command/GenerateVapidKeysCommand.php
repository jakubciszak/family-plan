<?php

declare(strict_types=1);

namespace App\Presentation\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-vapid-keys',
    description: 'Generate the key pair that signs push notifications',
)]
class GenerateVapidKeysCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keys = VAPID::createVapidKeys();

        $io->success('Key pair generated.');
        $io->writeln('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $io->writeln('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $io->newLine();
        $io->note([
            'Put both in the environment of every instance that sends notifications.',
            'Changing the public key invalidates every subscription browsers already hold.',
        ]);

        return Command::SUCCESS;
    }
}
