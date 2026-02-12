<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class HealthCheckTest extends WebTestCase
{
    public function testHealthCheckEndpointReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/healthcheck');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertResponseIsSuccessful();

        $responseContent = $client->getResponse()->getContent();
        $this->assertIsString($responseContent);

        $content = json_decode($responseContent, true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('status', $content);
        $this->assertSame('ok', $content['status']);
    }
}
