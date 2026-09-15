<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Search\OrderSearchIndex;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrderSearchControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private OrderSearchIndex $searchIndex;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->executeStatement('TRUNCATE orders RESTART IDENTITY');

        $this->searchIndex = self::getContainer()->get(OrderSearchIndex::class);
        $this->searchIndex->ensureTableExists();
        $this->searchIndex->truncate();
    }

    public function testMissingQueryReturnsBadRequest(): void
    {
        $this->client->request('GET', '/api/orders/search');

        self::assertResponseStatusCodeSame(400);
    }

    public function testSearchFindsIndexedOrder(): void
    {
        $order = new Order('John Doe', '42.50', new \DateTimeImmutable('2026-03-01T10:00:00+00:00'));
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $this->searchIndex->index($order);

        $this->client->request('GET', '/api/orders/search', ['q' => 'John']);

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame(1, $data['totalItems']);
        self::assertSame('John Doe', $data['items'][0]['customerName']);
        self::assertSame(42.5, $data['items'][0]['amount']);
    }

    public function testSearchWithNoMatchesReturnsEmptyItems(): void
    {
        $this->client->request('GET', '/api/orders/search', ['q' => 'NobodyWithThisName']);

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame(0, $data['totalItems']);
        self::assertSame([], $data['items']);
    }
}
