<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class OrderController
{
    public function __construct(private readonly OrderRepository $orderRepository)
    {
    }

    #[Route('/api/orders/{id}', name: 'api_order_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/orders/{id}',
        summary: 'Get a single order by id',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Order found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'customerName', type: 'string', example: 'John Doe'),
                        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 42.5),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2026-03-01T10:00:00+00:00'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function __invoke(int $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);

        if (null === $order) {
            return new JsonResponse(['error' => sprintf('Order %d not found', $id)], 404);
        }

        return new JsonResponse([
            'id' => $order->getId(),
            'customerName' => $order->getCustomerName(),
            'amount' => (float) $order->getAmount(),
            'createdAt' => $order->getCreatedAt()->format(DATE_ATOM),
        ]);
    }
}
