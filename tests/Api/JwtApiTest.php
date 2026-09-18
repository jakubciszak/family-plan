<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class JwtApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->client->restart();
    }

    public function testTokensAuthenticateWithoutASessionAndRefreshOnlyOnce(): void
    {
        $issued = $this->assertJsonResponse($this->postJson('/api/auth/token', [
            'email' => $this->currentUser->email()->value(),
            'password' => 'password123',
        ]));
        $this->assertSame($this->currentUser->id()->value(), $issued['user']['id']);
        $this->assertNotEmpty($issued['refresh_token']);

        $this->client->restart();
        $this->client->request('GET', '/api/auth/me', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $issued['token']]);
        $me = $this->assertJsonResponse($this->client->getResponse());
        $this->assertSame($issued['user']['id'], $me['id']);

        $this->client->restart();
        $renewed = $this->assertJsonResponse($this->postJson('/api/auth/token/refresh', [
            'refresh_token' => $issued['refresh_token'],
        ]));
        $this->assertNotSame($issued['refresh_token'], $renewed['refresh_token']);
        $this->assertSame($issued['user'], $renewed['user']);

        $this->client->restart();
        $response = $this->postJson('/api/auth/token/refresh', ['refresh_token' => $issued['refresh_token']]);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testInvalidPasswordDoesNotIssueTokens(): void
    {
        $body = $this->assertJsonResponse($this->postJson('/api/auth/token', [
            'email' => $this->currentUser->email()->value(),
            'password' => 'wrong-password',
        ]), 401);
        $this->assertArrayNotHasKey('token', $body);
        $this->assertArrayNotHasKey('refresh_token', $body);
    }

    public function testInvalidBearerTokenIsRejected(): void
    {
        $this->client->request('GET', '/api/auth/me', server: ['HTTP_AUTHORIZATION' => 'Bearer invalid']);
        $this->assertSame(401, $this->client->getResponse()->getStatusCode());
    }
}
