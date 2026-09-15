<?php

namespace App\Tests\Functional;

use App\TileExpert\TileExpertPriceFetcher;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PriceControllerTest extends WebTestCase
{
    private const PRODUCT_HTML = <<<'HTML'
        <span class="js-price-tag" data-measure="mq" data-price-raw="59.99">59,99</span>
        HTML;

    public function testMissingParametersReturnBadRequest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/price', ['factory' => 'marca-corona']);

        self::assertResponseStatusCodeSame(400);
    }

    public function testSuccessfulPriceLookup(): void
    {
        $client = static::createClient();
        self::getContainer()->set(
            TileExpertPriceFetcher::class,
            new TileExpertPriceFetcher(new MockHttpClient(new MockResponse(self::PRODUCT_HTML, ['http_code' => 200])))
        );

        $client->request('GET', '/api/price', [
            'factory' => 'marca-corona',
            'collection' => 'arteseta',
            'article' => 'k263-arteseta-camoscio-s000628660',
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            '{"price":59.99,"factory":"marca-corona","collection":"arteseta","article":"k263-arteseta-camoscio-s000628660"}',
            $client->getResponse()->getContent()
        );
    }

    public function testUnknownArticleReturnsNotFound(): void
    {
        $client = static::createClient();
        self::getContainer()->set(
            TileExpertPriceFetcher::class,
            new TileExpertPriceFetcher(new MockHttpClient(new MockResponse('', ['http_code' => 404])))
        );

        $client->request('GET', '/api/price', [
            'factory' => 'unknown',
            'collection' => 'unknown',
            'article' => 'unknown',
        ]);

        self::assertResponseStatusCodeSame(404);
    }
}
