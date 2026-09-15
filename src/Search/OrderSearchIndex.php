<?php

namespace App\Search;

use App\Entity\Order;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OrderSearchIndex
{
    private readonly string $table;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $manticoreUrl,
        string $environment,
    ) {
        $this->table = 'test' === $environment ? 'orders_test' : 'orders';
    }

    public function ensureTableExists(): void
    {
        $this->httpClient->request('POST', $this->manticoreUrl.'/sql', [
            'query' => ['mode' => 'raw'],
            'body' => 'CREATE TABLE IF NOT EXISTS '.$this->table.'(customer_name text, amount float, created_at timestamp)',
        ]);
    }

    public function truncate(): void
    {
        $this->httpClient->request('POST', $this->manticoreUrl.'/sql', [
            'query' => ['mode' => 'raw'],
            'body' => 'TRUNCATE TABLE '.$this->table,
        ]);
    }

    public function index(Order $order): void
    {
        $this->httpClient->request('POST', $this->manticoreUrl.'/replace', [
            'json' => [
                'table' => $this->table,
                'id' => $order->getId(),
                'doc' => [
                    'customer_name' => $order->getCustomerName(),
                    'amount' => (float) $order->getAmount(),
                    'created_at' => $order->getCreatedAt()->getTimestamp(),
                ],
            ],
        ]);
    }

    public function search(string $query, int $limit, int $offset): array
    {
        $response = $this->httpClient->request('POST', $this->manticoreUrl.'/search', [
            'json' => [
                'table' => $this->table,
                'query' => ['match' => ['customer_name' => $query]],
                'limit' => $limit,
                'offset' => $offset,
            ],
        ])->toArray();

        $hits = $response['hits']['hits'] ?? [];

        $items = array_map(
            static fn (array $hit): array => [
                'id' => (int) $hit['_id'],
                'customerName' => $hit['_source']['customer_name'],
                'amount' => (float) $hit['_source']['amount'],
                'createdAt' => (new \DateTimeImmutable('@'.$hit['_source']['created_at']))->format(DATE_ATOM),
            ],
            $hits
        );

        return [
            'total' => (int) ($response['hits']['total'] ?? 0),
            'items' => $items,
        ];
    }
}
