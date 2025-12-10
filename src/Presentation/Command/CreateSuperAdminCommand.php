<?php

declare(strict_types=1);

namespace App\Presentation\Command;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Application\Command\CreateUserCommand;
use App\UserManagement\Application\Handler\CreateUserHandler;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:create-super-admin',
    description: 'Create or update the super admin user from environment variables',
)]
class CreateSuperAdminCommand extends Command
{
    public function __construct(
        private readonly CreateUserHandler $createUserHandler,
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ParameterBagInterface $params
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $_ENV['SUPER_ADMIN_EMAIL'] ?? 'admin@familyplan.local';
        $name = $_ENV['SUPER_ADMIN_NAME'] ?? 'Super Admin';
        $plainPassword = $_ENV['SUPER_ADMIN_PASSWORD'] ?? 'admin123';

        try {
            $emailVO = Email::fromString($email);
            
            // Check if user already exists
            $existingUser = $this->userRepository->findByEmail($emailVO);
            
            if ($existingUser !== null) {
                // Update existing user's password
                $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
                $existingUser->changePassword($hashedPassword);
                
                if (!$existingUser->isAdmin()) {
                    $existingUser->promoteToAdmin();
                }
                
                $this->userRepository->save($existingUser);
                
                $io->success(sprintf('Super admin user "%s" updated successfully!', $email));
            } else {
                // Create new super admin user
                $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
                
                $command = new CreateUserCommand(
                    Uuid::generate(),
                    $name,
                    $emailVO,
                    $hashedPassword,
                    Role::ADMIN
                );
                
                ($this->createUserHandler)($command);
                
                $io->success(sprintf('Super admin user "%s" created successfully!', $email));
            }

            $io->table(
                ['Property', 'Value'],
                [
                    ['Email', $email],
                    ['Name', $name],
                    ['Password', str_repeat('*', strlen($plainPassword))],
                    ['Role', 'ADMIN'],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to create/update super admin: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
