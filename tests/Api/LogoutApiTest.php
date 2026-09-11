<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\Response;

class LogoutApiTest extends ApiTestCase
{
    public function testLogoutEndsTheSession(): void
    {
        $this->client->request('GET', '/api/auth/me');
        $this->assertResponseIsSuccessful();

        $this->assertSame(
            Response::HTTP_OK,
            $this->postJson('/api/auth/logout', [])->getStatusCode()
        );

        $this->client->request('GET', '/api/auth/me');
        $this->assertSame(
            Response::HTTP_UNAUTHORIZED,
            $this->client->getResponse()->getStatusCode(),
            'Refreshing after logout must not restore the session.'
        );
    }
}
