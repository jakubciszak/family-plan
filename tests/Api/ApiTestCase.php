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
}
