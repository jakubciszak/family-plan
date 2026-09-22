<?php

declare(strict_types=1);

namespace App\Tests\UserManagement\Application;

use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\UserManagement\Mother\UserMother;
use App\UserManagement\Application\Command\ResetUserPasswordCommand;
use App\UserManagement\Application\Handler\ResetUserPasswordHandler;
use App\UserManagement\Domain\Exception\PasswordTooShort;
use App\UserManagement\Domain\Exception\UserNotFound;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetUserPasswordHandlerTest extends TestCase
{
    private InMemoryUserRepository $repository;
    private ResetUserPasswordHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryUserRepository();

        $passwordHasher = new class implements UserPasswordHasherInterface {
            public function hashPassword(object $user, string $plainPassword): string
            {
                return password_hash($plainPassword, PASSWORD_BCRYPT);
            }

            public function isPasswordValid(object $user, string $plainPassword): bool
            {
                return password_verify($plainPassword, $user->getPassword());
            }

            public function needsRehash(object $user): bool
            {
                return false;
            }
        };

        $this->handler = new ResetUserPasswordHandler($this->repository, $passwordHasher);
    }

    public function testResetsPasswordToTheNewOne(): void
    {
        $userId = UuidMother::random();
        $user = UserMother::aUser()->withId($userId)->build();
        $this->repository->save($user);

        ($this->handler)(new ResetUserPasswordCommand($userId->value(), 'BrandNewPass123'));

        $stored = $this->repository->findById($userId);
        $this->assertNotNull($stored);
        $this->assertTrue(password_verify('BrandNewPass123', $stored->password()));
    }

    public function testOldPasswordStopsWorking(): void
    {
        $userId = UuidMother::random();
        $user = UserMother::aUser()
            ->withId($userId)
            ->withPassword(password_hash('OldPassword1', PASSWORD_BCRYPT))
            ->build();
        $this->repository->save($user);

        ($this->handler)(new ResetUserPasswordCommand($userId->value(), 'BrandNewPass123'));

        $stored = $this->repository->findById($userId);
        $this->assertNotNull($stored);
        $this->assertFalse(password_verify('OldPassword1', $stored->password()));
    }

    public function testPasswordIsNotStoredInPlainText(): void
    {
        $userId = UuidMother::random();
        $this->repository->save(UserMother::aUser()->withId($userId)->build());

        ($this->handler)(new ResetUserPasswordCommand($userId->value(), 'BrandNewPass123'));

        $stored = $this->repository->findById($userId);
        $this->assertNotNull($stored);
        $this->assertNotSame('BrandNewPass123', $stored->password());
    }

    public function testUnknownUserIsRejected(): void
    {
        $this->expectException(UserNotFound::class);

        ($this->handler)(new ResetUserPasswordCommand(UuidMother::random()->value(), 'BrandNewPass123'));
    }

    public function testShortPasswordIsRejected(): void
    {
        $userId = UuidMother::random();
        $this->repository->save(UserMother::aUser()->withId($userId)->build());

        $this->expectException(PasswordTooShort::class);

        ($this->handler)(new ResetUserPasswordCommand($userId->value(), 'short'));
    }

    public function testShortPasswordLeavesTheStoredOneUntouched(): void
    {
        $userId = UuidMother::random();
        $user = UserMother::aUser()
            ->withId($userId)
            ->withPassword(password_hash('OldPassword1', PASSWORD_BCRYPT))
            ->build();
        $this->repository->save($user);

        try {
            ($this->handler)(new ResetUserPasswordCommand($userId->value(), 'short'));
        } catch (PasswordTooShort) {
        }

        $stored = $this->repository->findById($userId);
        $this->assertNotNull($stored);
        $this->assertTrue(password_verify('OldPassword1', $stored->password()));
    }
}
