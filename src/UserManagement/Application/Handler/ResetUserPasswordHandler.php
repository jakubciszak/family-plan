<?php

declare(strict_types=1);

namespace App\UserManagement\Application\Handler;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Application\Command\ResetUserPasswordCommand;
use App\UserManagement\Domain\Exception\PasswordTooShort;
use App\UserManagement\Domain\Exception\UserNotFound;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ResetUserPasswordHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function __invoke(ResetUserPasswordCommand $command): void
    {
        if (mb_strlen($command->newPassword) < ResetUserPasswordCommand::MIN_PASSWORD_LENGTH) {
            throw new PasswordTooShort();
        }

        $user = $this->userRepository->findById(Uuid::fromString($command->userId));

        if ($user === null) {
            throw new UserNotFound();
        }

        $user->changePassword($this->passwordHasher->hashPassword($user, $command->newPassword));

        $this->userRepository->save($user);
    }
}
