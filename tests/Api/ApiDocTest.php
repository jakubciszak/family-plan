<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiDocTest extends WebTestCase
{
    public function testApiDocEndpointIsAccessible(): void
    {
        $client = static::createClient();
        $client->followRedirects();
        $client->request('GET', '/api/doc');

        // The endpoint should be accessible (200) after following redirects
        $this->assertResponseIsSuccessful();
    }
}
