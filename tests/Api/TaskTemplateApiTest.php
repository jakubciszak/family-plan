<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\Response;

class TaskTemplateApiTest extends ApiTestCase
{
    public function testTaskTemplatesEndpointIsExposedUnderTheApiPrefix(): void
    {
        $this->client->request('GET', '/api/task-templates', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $this->client->getResponse();

        $this->assertNotSame(
            Response::HTTP_NOT_FOUND,
            $response->getStatusCode(),
            'The frontend calls /api/task-templates; a 404 means the JSON endpoint is missing again.'
        );

        if ($response->getStatusCode() === Response::HTTP_OK) {
            $this->assertArrayHasKey('templates', json_decode($response->getContent(), true));
        }
    }
}
