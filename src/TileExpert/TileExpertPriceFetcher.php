<?php

namespace App\TileExpert;

use App\TileExpert\Exception\TileExpertArticleNotFoundException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TileExpertPriceFetcher
{
    private const BASE_URL = 'https://tile.expert/it/tile';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function fetch(string $factory, string $collection, string $article): float
    {
        $url = sprintf('%s/%s/%s/a/%s', self::BASE_URL, $factory, $collection, $article);

        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['User-Agent' => self::USER_AGENT],
        ]);

        $statusCode = $response->getStatusCode();
        $html = $response->getContent(false);

        if (200 !== $statusCode) {
            throw new TileExpertArticleNotFoundException($factory, $collection, $article);
        }

        $crawler = new Crawler($html);
        $priceNodes = $crawler->filter('.js-price-tag[data-measure="mq"]');

        if (0 === $priceNodes->count()) {
            throw new TileExpertArticleNotFoundException($factory, $collection, $article);
        }

        $priceRaw = $priceNodes->first()->attr('data-price-raw');

        if (null === $priceRaw || !is_numeric($priceRaw)) {
            throw new TileExpertArticleNotFoundException($factory, $collection, $article);
        }

        return (float) $priceRaw;
    }
}
