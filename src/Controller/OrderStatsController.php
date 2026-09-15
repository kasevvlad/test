<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class OrderStatsController
{
    private const ALLOWED_GROUPS = ['day', 'month', 'year'];
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly OrderRepository $orderRepository)
    {
    }

    #[Route('/api/orders/stats', name: 'api_orders_stats', methods: ['GET'])]
    #[OA\Get(
        path: '/api/orders/stats',
        summary: 'Get the number of orders grouped by day, month or year',
        parameters: [
            new OA\Parameter(name: 'group', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: self::ALLOWED_GROUPS), example: 'month'),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1), example: 1),
            new OA\Parameter(name: 'perPage', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: self::DEFAULT_PER_PAGE), example: 20),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Grouped order counts',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'perPage', type: 'integer', example: 20),
                        new OA\Property(property: 'totalItems', type: 'integer', example: 87),
                        new OA\Property(property: 'totalPages', type: 'integer', example: 5),
                        new OA\Property(property: 'group', type: 'string', example: 'month'),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(properties: [
                                new OA\Property(property: 'period', type: 'string', example: '2026-01'),
                                new OA\Property(property: 'count', type: 'integer', example: 12),
                            ])
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid group, page or perPage'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $group = $request->query->get('group');

        if (!in_array($group, self::ALLOWED_GROUPS, true)) {
            return new JsonResponse(['error' => 'group must be one of: '.implode(', ', self::ALLOWED_GROUPS)], 400);
        }

        $page = $request->query->getInt('page', 1);
        $perPage = $request->query->getInt('perPage', self::DEFAULT_PER_PAGE);

        if ($page < 1 || $perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            return new JsonResponse(['error' => sprintf('page must be >= 1 and perPage must be between 1 and %d', self::MAX_PER_PAGE)], 400);
        }

        $result = $this->orderRepository->countGroupedByPeriod($group, $perPage, ($page - 1) * $perPage);

        return new JsonResponse([
            'page' => $page,
            'perPage' => $perPage,
            'totalItems' => $result['total'],
            'totalPages' => (int) ceil($result['total'] / $perPage),
            'group' => $group,
            'items' => $result['rows'],
        ]);
    }
}
