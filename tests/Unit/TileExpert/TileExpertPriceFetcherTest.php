<?php

namespace App\Tests\Unit\TileExpert;

use App\TileExpert\Exception\TileExpertArticleNotFoundException;
use App\TileExpert\TileExpertPriceFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TileExpertPriceFetcherTest extends TestCase
{
    private const PRODUCT_HTML = <<<'HTML'
        <section class="wrap-price-block">
            <div class="full-price-block js-full-price-block">
                <ul class="list-unstyled animated-price js-animated-price">
                    <li data-animate="59.99">
                        <div class="price-per-measure-container">
                            <span class="js-price-tag" data-measure="mq" data-price-raw="59.99">59,99</span>
                        </div>
                    </li>
                    <li data-animate="53.99">
                        <div class="price-per-measure-container">
                            <span class="js-price-tag" data-measure="mq" data-price-raw="53.99">53,99</span>
                        </div>
                    </li>
                </ul>
            </div>
        </section>
        HTML;

    public function testFetchReturnsThePrimaryPrice(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::PRODUCT_HTML, ['http_code' => 200]));
        $fetcher = new TileExpertPriceFetcher($httpClient);

        $price = $fetcher->fetch('marca-corona', 'arteseta', 'k263-arteseta-camoscio-s000628660');

        self::assertSame(59.99, $price);
    }

    public function testFetchThrowsWhenPageIsMissing(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 404]));
        $fetcher = new TileExpertPriceFetcher($httpClient);

        $this->expectException(TileExpertArticleNotFoundException::class);

        $fetcher->fetch('unknown', 'unknown', 'unknown');
    }

    public function testFetchThrowsWhenPriceMarkupIsMissing(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('<html><body>no price here</body></html>', ['http_code' => 200]));
        $fetcher = new TileExpertPriceFetcher($httpClient);

        $this->expectException(TileExpertArticleNotFoundException::class);

        $fetcher->fetch('marca-corona', 'arteseta', 'k263-arteseta-camoscio-s000628660');
    }
}
