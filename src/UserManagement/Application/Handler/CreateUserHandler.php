<?php

declare(strict_types=1);

namespace App\UserManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Application\Command\CreateUserCommand;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function __invoke(CreateUserCommand $command): void
    {
        $user = User::create(
            Uuid::fromString($command->id),
            $command->name,
            Email::fromString($command->email),
            password_hash($command->password, PASSWORD_BCRYPT),
            Role::fromString($command->role)
        );

        $this->userRepository->save($user);
    }
}
