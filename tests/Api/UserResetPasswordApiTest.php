<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;

class UserResetPasswordApiTest extends ApiTestCase
{
    public function testAdminResetsAnotherAccountPassword(): void
    {
        $this->authenticate(Role::ADMIN);
        $target = $this->createAccount('OldPassword1');

        $response = $this->postJson(
            sprintf('/api/users/%s/reset-password', $target->id()->value()),
            ['newPassword' => 'BrandNewPass123']
        );

        $this->assertJsonResponse($response, 200);
        $this->assertTrue(password_verify('BrandNewPass123', $this->reload($target)->password()));
    }

    public function testOldPasswordStopsWorkingAfterReset(): void
    {
        $this->authenticate(Role::ADMIN);
        $target = $this->createAccount('OldPassword1');

        $this->postJson(
            sprintf('/api/users/%s/reset-password', $target->id()->value()),
            ['newPassword' => 'BrandNewPass123']
        );

        $this->assertFalse(password_verify('OldPassword1', $this->reload($target)->password()));
    }

    public function testRegularUserCannotResetSomeoneElsePassword(): void
    {
        $this->authenticate(Role::USER);
        $target = $this->createAccount('OldPassword1');

        $response = $this->postJson(
            sprintf('/api/users/%s/reset-password', $target->id()->value()),
            ['newPassword' => 'BrandNewPass123']
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertTrue(password_verify('OldPassword1', $this->reload($target)->password()));
    }

    public function testUnknownUserReturnsNotFound(): void
    {
        $this->authenticate(Role::ADMIN);

        $response = $this->postJson(
            sprintf('/api/users/%s/reset-password', Uuid::generate()->value()),
            ['newPassword' => 'BrandNewPass123']
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testShortPasswordIsRejected(): void
    {
        $this->authenticate(Role::ADMIN);
        $target = $this->createAccount('OldPassword1');

        $response = $this->postJson(
            sprintf('/api/users/%s/reset-password', $target->id()->value()),
            ['newPassword' => 'short']
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertTrue(password_verify('OldPassword1', $this->reload($target)->password()));
    }

    private function createAccount(string $plainPassword): User
    {
        $user = User::create(
            Uuid::generate(),
            'Reset Target',
            Email::fromString(sprintf('reset-target-%s@example.com', uniqid())),
            password_hash($plainPassword, PASSWORD_BCRYPT),
            Role::USER
        );

        static::getContainer()->get(UserRepositoryInterface::class)->save($user);

        return $user;
    }

    private function reload(User $user): User
    {
        $reloaded = static::getContainer()
            ->get(UserRepositoryInterface::class)
            ->findById($user->id());

        $this->assertNotNull($reloaded);

        return $reloaded;
    }
}
