<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SwaggerDocTest extends WebTestCase
{
    public function testSwaggerUiIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/doc');

        self::assertResponseIsSuccessful();
    }

    public function testSwaggerJsonIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/doc.json');

        self::assertResponseIsSuccessful();
        self::assertJson($client->getResponse()->getContent());
    }
}
