<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Shared\Domain\ValueObject\Uuid;
use App\Tests\TestDatabase;
use App\UserManagement\Domain\Entity\User;
use App\UserManagement\Domain\Repository\UserRepositoryInterface;
use App\UserManagement\Domain\ValueObject\Email;
use App\UserManagement\Domain\ValueObject\Role;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

final class RememberMeApiTest extends WebTestCase
{
    private const PASSWORD = 'password123';
    private const REMEMBER_ME_COOKIE = 'REMEMBERME';

    private KernelBrowser $client;

    private string $email;

    protected function setUp(): void
    {
        parent::setUp();
        TestDatabase::prepareOnce();

        $this->client = static::createClient();
        $this->email = sprintf('remember-me-%s@example.com', uniqid());

        static::getContainer()->get(UserRepositoryInterface::class)->save(User::create(
            Uuid::generate(),
            'Remember Me User',
            Email::fromString($this->email),
            password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            Role::USER
        ));
    }

    public function testLoginWithoutRememberMeDoesNotOutliveTheSession(): void
    {
        $this->login();

        $this->assertNull($this->client->getCookieJar()->get(self::REMEMBER_ME_COOKIE));

        $this->keepOnlyRememberMeCookie();

        $this->client->request('GET', '/api/auth/me');
        $this->assertSame(
            Response::HTTP_UNAUTHORIZED,
            $this->client->getResponse()->getStatusCode()
        );
    }

    public function testRememberedLoginOutlivesTheSession(): void
    {
        $this->login(rememberMe: true);

        $this->assertNotNull($this->client->getCookieJar()->get(self::REMEMBER_ME_COOKIE));

        $this->keepOnlyRememberMeCookie();

        $this->client->request('GET', '/api/auth/me');
        $this->assertResponseIsSuccessful();
        $this->assertSame(
            $this->email,
            json_decode($this->client->getResponse()->getContent(), true)['email']
        );
    }

    public function testLogoutRevokesTheRememberMeCookie(): void
    {
        $this->login(rememberMe: true);

        $this->client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{}');
        $this->assertResponseIsSuccessful();

        $this->assertNull($this->client->getCookieJar()->get(self::REMEMBER_ME_COOKIE));

        $this->keepOnlyRememberMeCookie();

        $this->client->request('GET', '/api/auth/me');
        $this->assertSame(
            Response::HTTP_UNAUTHORIZED,
            $this->client->getResponse()->getStatusCode()
        );
    }

    private function login(bool $rememberMe = false): void
    {
        $payload = ['email' => $this->email, 'password' => self::PASSWORD];

        if ($rememberMe) {
            $payload['_remember_me'] = true;
        }

        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();
    }

    private function keepOnlyRememberMeCookie(): void
    {
        $jar = $this->client->getCookieJar();
        $rememberMe = $jar->get(self::REMEMBER_ME_COOKIE);
        $jar->clear();

        if ($rememberMe instanceof Cookie) {
            $jar->set($rememberMe);
        }
    }
}
