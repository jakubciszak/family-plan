<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    protected function getJson(string $uri): array
    {
        $this->client->request('GET', $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        return json_decode($response->getContent(), true);
    }

    protected function postJson(string $uri, array $data): Response
    {
        $this->client->request('POST', $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($data));

        return $this->client->getResponse();
    }

    protected function putJson(string $uri, array $data): Response
    {
        $this->client->request('PUT', $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($data));

        return $this->client->getResponse();
    }

    protected function deleteJson(string $uri): Response
    {
        $this->client->request('DELETE', $uri, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        return $this->client->getResponse();
    }

    protected function assertJsonResponse(Response $response, int $statusCode = 200): array
    {
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        return json_decode($response->getContent(), true);
    }

    /**
     * Create a team and admin user directly using command bus (bypasses HTTP layer)
     */
    protected function createTeamAndAdmin(): array
    {
        // Create admin user via HTTP (this works)
        $adminData = [
            'name' => 'Admin User',
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => 'adminpass123',
            'role' => 'ROLE_ADMIN',
        ];
        $adminResponse = $this->postJson('/api/users', $adminData);
        $admin = $this->assertJsonResponse($adminResponse, 201);

        // Create team directly using command bus
        $container = static::getContainer();
        $commandBus = $container->get('command.bus');

        $teamId = \App\Shared\Domain\ValueObject\Uuid::generate()->value();
        $createTeamCommand = new \App\TeamManagement\Application\Command\CreateTeamCommand(
            $teamId,
            'Test Team',
            'Test Description',
            $admin['id']
        );

        $commandBus->dispatch($createTeamCommand);

        return [
            'adminId' => $admin['id'],
            'teamId' => $teamId,
        ];
    }
}
