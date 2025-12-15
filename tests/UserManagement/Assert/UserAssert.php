<?php

declare(strict_types=1);

namespace App\Tests\UserManagement\Assert;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Event\UserCreated;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use PHPUnit\Framework\Assert;

final class UserAssert
{
    public static function assertUserHasId(Uuid $expectedId, User $user): void
    {
        Assert::assertTrue(
            $expectedId->equals($user->id()),
            sprintf('Expected user ID to be %s, but got %s', $expectedId->value(), $user->id()->value())
        );
    }

    public static function assertUserHasName(string $expectedName, User $user): void
    {
        Assert::assertSame(
            $expectedName,
            $user->name(),
            sprintf('Expected user name to be "%s", but got "%s"', $expectedName, $user->name())
        );
    }

    public static function assertUserHasEmail(Email $expectedEmail, User $user): void
    {
        Assert::assertTrue(
            $expectedEmail->equals($user->email()),
            sprintf('Expected user email to be %s, but got %s', $expectedEmail->value(), $user->email()->value())
        );
    }

    public static function assertUserHasRole(Role $expectedRole, User $user): void
    {
        Assert::assertSame(
            $expectedRole,
            $user->role(),
            sprintf('Expected user role to be %s, but got %s', $expectedRole->value, $user->role()->value)
        );
    }

    public static function assertUserIsAdmin(User $user): void
    {
        Assert::assertTrue(
            $user->isAdmin(),
            'Expected user to be an admin, but they are not'
        );
    }

    public static function assertUserIsNotAdmin(User $user): void
    {
        Assert::assertFalse(
            $user->isAdmin(),
            'Expected user to not be an admin, but they are'
        );
    }

    public static function assertUserHasCreatedAt(User $user): void
    {
        Assert::assertInstanceOf(
            \DateTimeImmutable::class,
            $user->createdAt(),
            'Expected user to have a createdAt timestamp'
        );
    }

    public static function assertUserHasUpdatedAt(User $user): void
    {
        Assert::assertNotNull(
            $user->updatedAt(),
            'Expected user to have an updatedAt timestamp'
        );
        Assert::assertInstanceOf(
            \DateTimeImmutable::class,
            $user->updatedAt(),
            'Expected updatedAt to be a DateTimeImmutable'
        );
    }

    public static function assertUserHasNoUpdatedAt(User $user): void
    {
        Assert::assertNull(
            $user->updatedAt(),
            'Expected user to not have an updatedAt timestamp'
        );
    }

    public static function assertUserRecordedDomainEvent(string $eventClass, User $user): void
    {
        $events = $user->pullDomainEvents();
        $found = false;

        foreach ($events as $event) {
            if ($event instanceof $eventClass) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue(
            $found,
            sprintf('Expected user to have recorded a %s event', $eventClass)
        );
    }

    public static function assertUserRecordedUserCreatedEvent(User $user, Uuid $userId, Email $email, Role $role): void
    {
        $events = $user->pullDomainEvents();
        Assert::assertCount(1, $events, 'Expected exactly one domain event');
        
        $event = $events[0];
        Assert::assertInstanceOf(UserCreated::class, $event, 'Expected event to be UserCreated');
        
        Assert::assertTrue(
            $userId->equals($event->userId()),
            'Expected UserCreated event to have matching user ID'
        );
        Assert::assertTrue(
            $email->equals($event->email()),
            'Expected UserCreated event to have matching email'
        );
        Assert::assertSame(
            $role,
            $event->role(),
            'Expected UserCreated event to have matching role'
        );
    }

    public static function assertUserHasNoDomainEvents(User $user): void
    {
        $events = $user->pullDomainEvents();
        Assert::assertCount(
            0,
            $events,
            sprintf('Expected user to have no domain events, but found %d', count($events))
        );
    }

    public static function assertEmailIsValid(string $emailString): void
    {
        Assert::assertTrue(
            filter_var($emailString, FILTER_VALIDATE_EMAIL) !== false,
            sprintf('Expected "%s" to be a valid email address', $emailString)
        );
    }

    public static function assertUuidIsValid(string $uuidString): void
    {
        Assert::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuidString,
            sprintf('Expected "%s" to be a valid UUID v4', $uuidString)
        );
    }
}
