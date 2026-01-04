<?php

declare(strict_types=1);

namespace App\UserManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Application\Command\RegisterUserCommand;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function __invoke(RegisterUserCommand $command): void
    {
        // Check if user with this email already exists
        $existingUser = $this->userRepository->findByEmail(Email::fromString($command->email));
        if ($existingUser !== null) {
            throw new \DomainException('User with this email already exists');
        }

        $user = User::register(
            Uuid::fromString($command->id),
            $command->name,
            Email::fromString($command->email),
            password_hash($command->password, PASSWORD_BCRYPT),
            $command->phoneNumber
        );

        $this->userRepository->save($user);
    }
}
