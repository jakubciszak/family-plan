<?php

declare(strict_types=1);

namespace App\Tests\UserManagement\Application;

use App\UserManagement\Application\Command\CreateUserCommand;
use App\UserManagement\Application\Handler\CreateUserHandler;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\UserManagement\Assert\UserAssert;
use App\Tests\UserManagement\Mother\EmailMother;
use App\Tests\UserManagement\Mother\RoleMother;
use PHPUnit\Framework\TestCase;

/**
 * Detroit school test for CreateUserHandler
 * Tests the complete use case with real repository implementation
 */
class CreateUserHandlerTest extends TestCase
{
    private InMemoryUserRepository $repository;
    private CreateUserHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryUserRepository();
        $this->handler = new CreateUserHandler($this->repository);
    }

    public function testHandlerCreatesUserAndPersistsToRepository(): void
    {
        // Given
        $userId = UuidMother::random();
        $name = 'John Doe';
        $email = EmailMother::user();
        $password = password_hash('password123', PASSWORD_BCRYPT);
        $role = RoleMother::user();

        $command = new CreateUserCommand(
            $userId,
            $name,
            $email,
            $password,
            $role
        );

        // When
        ($this->handler)($command);

        // Then
        $persistedUser = $this->repository->findById($userId);
        $this->assertNotNull($persistedUser);
        UserAssert::assertUserHasId($userId, $persistedUser);
        UserAssert::assertUserHasName($name, $persistedUser);
        UserAssert::assertUserHasEmail($email, $persistedUser);
        UserAssert::assertUserHasRole($role, $persistedUser);
    }

    public function testHandlerCreatesAdminUser(): void
    {
        // Given
        $userId = UuidMother::random();
        $adminRole = RoleMother::admin();

        $command = new CreateUserCommand(
            $userId,
            'Admin User',
            EmailMother::admin(),
            password_hash('secure', PASSWORD_BCRYPT),
            $adminRole
        );

        // When
        ($this->handler)($command);

        // Then
        $persistedUser = $this->repository->findById($userId);
        $this->assertNotNull($persistedUser);
        UserAssert::assertUserIsAdmin($persistedUser);
    }

    public function testHandlerCreatedUserCanBeFoundByEmail(): void
    {
        // Given
        $email = EmailMother::create('unique@example.com');
        $command = new CreateUserCommand(
            UuidMother::random(),
            'Test User',
            $email,
            password_hash('pass', PASSWORD_BCRYPT),
            RoleMother::user()
        );

        // When
        ($this->handler)($command);

        // Then
        $foundUser = $this->repository->findByEmail($email);
        $this->assertNotNull($foundUser);
        UserAssert::assertUserHasEmail($email, $foundUser);
    }

    public function testRepositoryContainsCreatedUser(): void
    {
        // Given
        $firstUserId = UuidMother::random();
        $secondUserId = UuidMother::random();

        $firstCommand = new CreateUserCommand(
            $firstUserId,
            'First User',
            EmailMother::create('first@example.com'),
            password_hash('pass', PASSWORD_BCRYPT),
            RoleMother::user()
        );

        $secondCommand = new CreateUserCommand(
            $secondUserId,
            'Second User',
            EmailMother::create('second@example.com'),
            password_hash('pass', PASSWORD_BCRYPT),
            RoleMother::admin()
        );

        // When
        ($this->handler)($firstCommand);
        ($this->handler)($secondCommand);

        // Then
        $allUsers = $this->repository->findAll();
        $this->assertCount(2, $allUsers);
    }
}
