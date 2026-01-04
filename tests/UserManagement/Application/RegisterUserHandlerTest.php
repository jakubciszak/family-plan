<?php

declare(strict_types=1);

namespace App\Tests\UserManagement\Application;

use App\UserManagement\Application\Command\RegisterUserCommand;
use App\UserManagement\Application\Handler\RegisterUserHandler;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use App\Tests\Shared\Mother\UuidMother;
use App\Tests\UserManagement\Mother\EmailMother;
use PHPUnit\Framework\TestCase;

/**
 * Test for RegisterUserHandler
 */
class RegisterUserHandlerTest extends TestCase
{
    private InMemoryUserRepository $repository;
    private RegisterUserHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryUserRepository();
        $this->handler = new RegisterUserHandler($this->repository);
    }

    public function testHandlerRegistersUserAndPersistsToRepository(): void
    {
        // Given
        $userId = UuidMother::random();
        $name = 'John Doe';
        $email = EmailMother::user();
        $password = 'password123';
        $phoneNumber = '+48123456789';

        $command = new RegisterUserCommand(
            $userId->value(),
            $name,
            $email->value(),
            $password,
            $phoneNumber
        );

        // When
        ($this->handler)($command);

        // Then
        $persistedUser = $this->repository->findById($userId);
        $this->assertNotNull($persistedUser);
        $this->assertEquals($userId->value(), $persistedUser->id()->value());
        $this->assertEquals($name, $persistedUser->name());
        $this->assertEquals($email->value(), $persistedUser->email()->value());
        $this->assertEquals($phoneNumber, $persistedUser->phoneNumber());
        $this->assertFalse($persistedUser->isActive(), 'Registered user should be inactive');
        $this->assertNotNull($persistedUser->activationToken(), 'Activation token should be set');
    }

    public function testHandlerRegistersUserWithoutPhoneNumber(): void
    {
        // Given
        $userId = UuidMother::random();
        $command = new RegisterUserCommand(
            $userId->value(),
            'Test User',
            EmailMother::create('test@example.com')->value(),
            'securePassword123'
        );

        // When
        ($this->handler)($command);

        // Then
        $persistedUser = $this->repository->findById($userId);
        $this->assertNotNull($persistedUser);
        $this->assertNull($persistedUser->phoneNumber());
        $this->assertFalse($persistedUser->isActive());
    }

    public function testHandlerThrowsExceptionWhenEmailAlreadyExists(): void
    {
        // Given
        $email = EmailMother::create('existing@example.com');
        
        // Register first user
        $firstCommand = new RegisterUserCommand(
            UuidMother::random()->value(),
            'First User',
            $email->value(),
            'password123'
        );
        ($this->handler)($firstCommand);

        // Expect exception when registering second user with same email
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User with this email already exists');

        // When
        $secondCommand = new RegisterUserCommand(
            UuidMother::random()->value(),
            'Second User',
            $email->value(),
            'password456'
        );
        ($this->handler)($secondCommand);
    }

    public function testRegisteredUserHasRoleUser(): void
    {
        // Given
        $userId = UuidMother::random();
        $command = new RegisterUserCommand(
            $userId->value(),
            'Regular User',
            EmailMother::user()->value(),
            'password123'
        );

        // When
        ($this->handler)($command);

        // Then
        $user = $this->repository->findById($userId);
        $this->assertNotNull($user);
        $this->assertEquals('ROLE_USER', $user->role()->value);
    }
}
