<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\Response;

class AuthRegistrationApiTest extends ApiTestCase
{
    public function testRegisterAcceptsJsonPayload(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Api Test User',
            'email' => sprintf('api-register-%s@example.com', uniqid()),
            'password' => 'securePassword123',
        ]);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('activationRequired', $payload);
    }

    public function testRegisterRejectsInvalidPayload(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Api Test User',
        ]);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $email = sprintf('api-duplicate-%s@example.com', uniqid());
        $payload = [
            'name' => 'Api Test User',
            'email' => $email,
            'password' => 'securePassword123',
        ];

        $this->assertSame(Response::HTTP_CREATED, $this->postJson('/api/auth/register', $payload)->getStatusCode());
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->postJson('/api/auth/register', $payload)->getStatusCode());
    }
}
