<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\Response;

class ChangePasswordApiTest extends ApiTestCase
{
    public function testPasswordIsChangedWhenTheCurrentOneMatches(): void
    {
        $email = $this->currentUser->email()->value();

        $this->assertSame(
            Response::HTTP_OK,
            $this->postJson('/api/auth/change-password', [
                'currentPassword' => 'password123',
                'newPassword' => 'NoweHaslo456',
            ])->getStatusCode()
        );

        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $email, 'password' => 'password123']));
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());

        $this->client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['email' => $email, 'password' => 'NoweHaslo456']));
        $this->assertResponseIsSuccessful();
    }

    public function testWrongCurrentPasswordIsRejected(): void
    {
        $response = $this->postJson('/api/auth/change-password', [
            'currentPassword' => 'zupelnie-zle',
            'newPassword' => 'NoweHaslo456',
        ]);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testShortPasswordIsRejected(): void
    {
        $response = $this->postJson('/api/auth/change-password', [
            'currentPassword' => 'cokolwiek',
            'newPassword' => 'krotkie',
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }
}
