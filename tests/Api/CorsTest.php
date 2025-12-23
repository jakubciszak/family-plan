<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CorsTest extends WebTestCase
{
    /**
     * Test that CORS headers are present on a simple GET request
     */
    public function testCorsHeadersOnGetRequest(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/tasks', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $client->getResponse();
        
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    /**
     * Test that CORS headers are present on POST request
     */
    public function testCorsHeadersOnPostRequest(): void
    {
        $client = static::createClient();
        
        $taskData = [
            'name' => 'CORS Test Task',
            'description' => 'Testing CORS',
            'points' => 50,
            'frequency' => 'daily',
        ];

        $client->request('POST', '/api/tasks', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($taskData));

        $response = $client->getResponse();
        
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    /**
     * Test preflight OPTIONS request handling
     */
    public function testPreflightOptionsRequest(): void
    {
        $client = static::createClient();
        
        $client->request('OPTIONS', '/api/tasks', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type',
        ]);

        $response = $client->getResponse();
        
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Methods'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Headers'));
        $this->assertTrue($response->headers->has('Access-Control-Max-Age'));
        
        $this->assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('POST', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('content-type', strtolower($response->headers->get('Access-Control-Allow-Headers')));
        $this->assertSame('3600', $response->headers->get('Access-Control-Max-Age'));
    }

    /**
     * Test that all common HTTP methods are allowed
     */
    public function testAllowedMethodsInCorsHeaders(): void
    {
        $client = static::createClient();
        
        $client->request('OPTIONS', '/api/tasks', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $response = $client->getResponse();
        $allowedMethods = $response->headers->get('Access-Control-Allow-Methods');
        
        $this->assertStringContainsString('GET', $allowedMethods);
        $this->assertStringContainsString('POST', $allowedMethods);
        $this->assertStringContainsString('PUT', $allowedMethods);
        $this->assertStringContainsString('DELETE', $allowedMethods);
        $this->assertStringContainsString('PATCH', $allowedMethods);
        $this->assertStringContainsString('OPTIONS', $allowedMethods);
    }

    /**
     * Test that required headers are allowed
     */
    public function testAllowedHeadersInCorsHeaders(): void
    {
        $client = static::createClient();
        
        $client->request('OPTIONS', '/api/tasks', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type',
        ]);

        $response = $client->getResponse();
        $allowedHeaders = strtolower($response->headers->get('Access-Control-Allow-Headers'));
        
        $this->assertStringContainsString('content-type', $allowedHeaders);
        $this->assertStringContainsString('authorization', $allowedHeaders);
        $this->assertStringContainsString('x-requested-with', $allowedHeaders);
        $this->assertStringContainsString('accept', $allowedHeaders);
    }

    /**
     * Test CORS with different origin (not in allowed list)
     */
    public function testCorsWithUnallowedOrigin(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/tasks', [], [], [
            'HTTP_ORIGIN' => 'http://malicious-site.com',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $client->getResponse();
        
        // Should not have CORS headers for unallowed origin
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    /**
     * Test that credentials flag is properly set
     */
    public function testCredentialsAllowed(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/api/tasks', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $client->getResponse();
        
        $this->assertTrue($response->headers->has('Access-Control-Allow-Credentials'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    /**
     * Test PUT request with CORS
     */
    public function testCorsOnPutRequest(): void
    {
        $client = static::createClient();
        
        // First create a task to update
        $taskData = [
            'name' => 'Task to Update',
            'description' => 'Original description',
            'points' => 50,
            'frequency' => 'daily',
        ];

        $client->request('POST', '/api/tasks', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($taskData));

        $createResponse = $client->getResponse();
        $this->assertSame(201, $createResponse->getStatusCode());
        
        // Verify CORS headers on POST
        $this->assertTrue($createResponse->headers->has('Access-Control-Allow-Origin'));
        $this->assertSame('http://localhost:3000', $createResponse->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Test DELETE request with CORS
     */
    public function testCorsOnDeleteRequest(): void
    {
        $client = static::createClient();
        
        // Test OPTIONS request for DELETE (preflight)
        $client->request('OPTIONS', '/api/tasks/some-id', [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'DELETE',
        ]);

        $response = $client->getResponse();
        
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('DELETE', $response->headers->get('Access-Control-Allow-Methods'));
    }
}
