<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiDocTest extends WebTestCase
{
    public function testApiDocEndpointReturnsSwaggerUI(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/doc');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('swagger-ui', $content);
        $this->assertStringContainsString('Family Plan API Documentation', $content);
    }

    public function testOpenApiSpecEndpointReturnsYaml(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/openapi.yaml');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/x-yaml');
        
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('openapi:', $content);
        $this->assertStringContainsString('Family Plan API', $content);
    }
}
