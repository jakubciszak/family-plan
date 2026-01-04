<?php

declare(strict_types=1);

namespace App\Tests\UserManagement\Application;

use App\UserManagement\Application\Command\RegisterUserCommand;
use App\UserManagement\Application\Handler\RegisterUserHandler;
use App\UserManagement\Infrastructure\Persistence\InMemoryUserRepository;
use App\UserManagement\Domain\Event\UserRegistered;
use App\Tests\Shared\Mother\UuidMother;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for user registration flow
 */
class UserRegistrationIntegrationTest extends TestCase
{
    private InMemoryUserRepository $repository;
    private RegisterUserHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryUserRepository();
        $this->handler = new RegisterUserHandler($this->repository);
    }

    public function testUserRegistrationEmitsUserRegisteredEvent(): void
    {
        // Given
        $userId = UuidMother::random();
        $name = 'John Doe';
        $email = 'john@example.com';
        
        $command = new RegisterUserCommand(
            $userId->value(),
            $name,
            $email,
            'password123',
            '+48123456789'
        );

        // When
        ($this->handler)($command);

        // Then
        $user = $this->repository->findById($userId);
        $this->assertNotNull($user);
        
        $events = $user->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserRegistered::class, $events[0]);
        
        /** @var UserRegistered $event */
        $event = $events[0];
        $this->assertEquals($userId->value(), $event->userId()->value());
        $this->assertEquals($email, $event->email()->value());
        $this->assertEquals($name, $event->name());
        $this->assertNotNull($event->activationToken());
    }

    public function testCompleteRegistrationAndActivationFlow(): void
    {
        // Given - Register a new user
        $userId = UuidMother::random();
        $email = 'newuser@example.com';
        
        $registerCommand = new RegisterUserCommand(
            $userId->value(),
            'New User',
            $email,
            'securePassword123',
            null
        );

        // When - Register the user
        ($this->handler)($registerCommand);
        
        $user = $this->repository->findById($userId);
        $this->assertNotNull($user);
        $this->assertFalse($user->isActive());
        
        $activationToken = $user->activationToken();
        $this->assertNotNull($activationToken);

        // When - Activate the user
        $user->activate($activationToken);
        $this->repository->save($user);

        // Then - User should be active
        $activatedUser = $this->repository->findById($userId);
        $this->assertTrue($activatedUser->isActive());
        $this->assertNull($activatedUser->activationToken());
    }

    public function testRegisteredUserCannotLoginBeforeActivation(): void
    {
        // Given
        $userId = UuidMother::random();
        $command = new RegisterUserCommand(
            $userId->value(),
            'Inactive User',
            'inactive@example.com',
            'password123'
        );

        // When
        ($this->handler)($command);

        // Then
        $user = $this->repository->findById($userId);
        $this->assertNotNull($user);
        $this->assertFalse($user->isActive());
        
        // The UserChecker in the security system should prevent login
        // This is tested by the UserChecker itself
    }
}
