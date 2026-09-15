<?php

namespace App\Controller;

use App\TileExpert\Exception\TileExpertArticleNotFoundException;
use App\TileExpert\TileExpertPriceFetcher;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class PriceController
{
    public function __construct(private readonly TileExpertPriceFetcher $priceFetcher)
    {
    }

    #[Route('/api/price', name: 'api_price', methods: ['GET'])]
    #[OA\Get(
        path: '/api/price',
        summary: 'Get the price in EUR of a tile.expert article',
        parameters: [
            new OA\Parameter(name: 'factory', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: 'marca-corona'),
            new OA\Parameter(name: 'collection', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: 'arteseta'),
            new OA\Parameter(name: 'article', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: 'k263-arteseta-camoscio-s000628660'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Price found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'price', type: 'number', format: 'float', example: 59.99),
                        new OA\Property(property: 'factory', type: 'string', example: 'marca-corona'),
                        new OA\Property(property: 'collection', type: 'string', example: 'arteseta'),
                        new OA\Property(property: 'article', type: 'string', example: 'k263-arteseta-camoscio-s000628660'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Missing required query parameter'),
            new OA\Response(response: 404, description: 'Article not found on tile.expert'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $factory = $request->query->get('factory');
        $collection = $request->query->get('collection');
        $article = $request->query->get('article');

        if (!$factory || !$collection || !$article) {
            return new JsonResponse(['error' => 'factory, collection and article are required'], 400);
        }

        try {
            $price = $this->priceFetcher->fetch($factory, $collection, $article);
        } catch (TileExpertArticleNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        }

        return new JsonResponse([
            'price' => $price,
            'factory' => $factory,
            'collection' => $collection,
            'article' => $article,
        ]);
    }
}
