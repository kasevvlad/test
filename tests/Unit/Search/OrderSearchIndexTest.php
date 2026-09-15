<?php

namespace App\Tests\Unit\Search;

use App\Search\OrderSearchIndex;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OrderSearchIndexTest extends TestCase
{
    public function testSearchParsesHitsIntoItems(): void
    {
        $responseBody = json_encode([
            'hits' => [
                'total' => 1,
                'hits' => [
                    [
                        '_id' => 1,
                        '_source' => [
                            'customer_name' => 'John Doe',
                            'amount' => 42.5,
                            'created_at' => 1772359200,
                        ],
                    ],
                ],
            ],
        ]);

        $httpClient = new MockHttpClient(new MockResponse($responseBody, ['http_code' => 200]));
        $index = new OrderSearchIndex($httpClient, 'http://manticore:9308', 'test');

        $result = $index->search('John', 20, 0);

        self::assertSame(1, $result['total']);
        self::assertSame(1, $result['items'][0]['id']);
        self::assertSame('John Doe', $result['items'][0]['customerName']);
        self::assertSame(42.5, $result['items'][0]['amount']);
        self::assertSame('2026-03-01T10:00:00+00:00', $result['items'][0]['createdAt']);
    }

    public function testSearchReturnsEmptyResultWhenNoHits(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode(['hits' => ['total' => 0, 'hits' => []]]), ['http_code' => 200]));
        $index = new OrderSearchIndex($httpClient, 'http://manticore:9308', 'test');

        $result = $index->search('nothing', 20, 0);

        self::assertSame(0, $result['total']);
        self::assertSame([], $result['items']);
    }
}
