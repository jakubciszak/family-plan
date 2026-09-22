<?php

declare(strict_types=1);

namespace App\Presentation\Command;

use App\UserManagement\Application\Command\ResetUserPasswordCommand;
use App\UserManagement\Application\Handler\ResetUserPasswordHandler;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reset-password',
    description: 'Reset the password of an existing account',
)]
final class ResetPasswordCommand extends Command
{
    private const int GENERATED_PASSWORD_BYTES = 9;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ResetUserPasswordHandler $resetUserPassword
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address of the account')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'New password; asked interactively when omitted')
            ->addOption('generate', 'g', InputOption::VALUE_NONE, 'Generate a random password and print it once');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $email */
        $email = $input->getArgument('email');

        try {
            $user = $this->userRepository->findByEmail(Email::fromString($email));
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        if ($user === null) {
            $io->error(sprintf('No account found for "%s".', $email));

            return Command::FAILURE;
        }

        $generated = $input->getOption('generate') ? self::randomPassword() : null;
        $password = $generated ?? $input->getOption('password') ?? $this->askForPassword($input, $io);

        if ($password === null) {
            return Command::INVALID;
        }

        if (mb_strlen($password) < ResetUserPasswordCommand::MIN_PASSWORD_LENGTH) {
            $io->error(sprintf(
                'Password must be at least %d characters long.',
                ResetUserPasswordCommand::MIN_PASSWORD_LENGTH
            ));

            return Command::INVALID;
        }

        ($this->resetUserPassword)(new ResetUserPasswordCommand($user->id()->value(), $password));

        $io->success(sprintf('Password for "%s" has been reset.', $email));

        if ($generated !== null) {
            $io->writeln(sprintf('Generated password: <info>%s</info>', $generated));
            $io->warning('Shown once - pass it on over a secure channel and have the owner change it.');
        }

        return Command::SUCCESS;
    }

    private function askForPassword(InputInterface $input, SymfonyStyle $io): ?string
    {
        if (!$input->isInteractive()) {
            $io->error('No password given. Use --password or --generate in a non-interactive shell.');

            return null;
        }

        $password = $io->askHidden('New password');

        if ($password === null) {
            $io->error('No password given.');

            return null;
        }

        if ($password !== $io->askHidden('Repeat password')) {
            $io->error('Passwords do not match.');

            return null;
        }

        return $password;
    }

    private static function randomPassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::GENERATED_PASSWORD_BYTES)), '+/', 'Aa'), '=');
    }
}
