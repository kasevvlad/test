<?php

namespace App\Controller;

use App\Search\OrderSearchIndex;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class OrderSearchController
{
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly OrderSearchIndex $searchIndex)
    {
    }

    #[Route('/api/orders/search', name: 'api_orders_search', methods: ['GET'])]
    #[OA\Get(
        path: '/api/orders/search',
        summary: 'Full-text search orders by customer name (powered by Manticore Search)',
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: 'John'),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1), example: 1),
            new OA\Parameter(name: 'perPage', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: self::DEFAULT_PER_PAGE), example: 20),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Matching orders',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'perPage', type: 'integer', example: 20),
                        new OA\Property(property: 'totalItems', type: 'integer', example: 1),
                        new OA\Property(property: 'totalPages', type: 'integer', example: 1),
                        new OA\Property(property: 'query', type: 'string', example: 'John'),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'customerName', type: 'string', example: 'John Doe'),
                                new OA\Property(property: 'amount', type: 'number', format: 'float', example: 42.5),
                                new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2026-03-01T10:00:00+00:00'),
                            ])
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Missing q, invalid page or perPage'),
            new OA\Response(response: 503, description: 'Search backend unavailable'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        if ('' === $query) {
            return new JsonResponse(['error' => 'q is required'], 400);
        }

        $page = $request->query->getInt('page', 1);
        $perPage = $request->query->getInt('perPage', self::DEFAULT_PER_PAGE);

        if ($page < 1 || $perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            return new JsonResponse(['error' => sprintf('page must be >= 1 and perPage must be between 1 and %d', self::MAX_PER_PAGE)], 400);
        }

        try {
            $result = $this->searchIndex->search($query, $perPage, ($page - 1) * $perPage);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Search backend is unavailable'], 503);
        }

        return new JsonResponse([
            'page' => $page,
            'perPage' => $perPage,
            'totalItems' => $result['total'],
            'totalPages' => (int) ceil($result['total'] / $perPage),
            'query' => $query,
            'items' => $result['items'],
        ]);
    }
}
