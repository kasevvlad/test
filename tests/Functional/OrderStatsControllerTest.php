<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrderStatsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->executeStatement('TRUNCATE orders RESTART IDENTITY');
    }

    public function testInvalidGroupReturnsBadRequest(): void
    {
        $this->client->request('GET', '/api/orders/stats', ['group' => 'week']);

        self::assertResponseStatusCodeSame(400);
    }

    public function testInvalidPaginationReturnsBadRequest(): void
    {
        $this->client->request('GET', '/api/orders/stats', ['group' => 'day', 'page' => 0]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testGroupingByMonth(): void
    {
        $this->persistOrder('2026-01-05');
        $this->persistOrder('2026-01-15');
        $this->persistOrder('2026-02-01');
        $this->entityManager->flush();

        $this->client->request('GET', '/api/orders/stats', ['group' => 'month', 'perPage' => 10]);

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame(1, $data['page']);
        self::assertSame(2, $data['totalItems']);
        self::assertSame('month', $data['group']);
        self::assertSame(
            [
                ['period' => '2026-02', 'count' => 1],
                ['period' => '2026-01', 'count' => 2],
            ],
            $data['items']
        );
    }

    public function testPagination(): void
    {
        $this->persistOrder('2026-01-01');
        $this->persistOrder('2026-01-02');
        $this->persistOrder('2026-01-03');
        $this->entityManager->flush();

        $this->client->request('GET', '/api/orders/stats', ['group' => 'day', 'page' => 2, 'perPage' => 2]);

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame(3, $data['totalItems']);
        self::assertSame(2, $data['totalPages']);
        self::assertCount(1, $data['items']);
        self::assertSame(['period' => '2026-01-01', 'count' => 1], $data['items'][0]);
    }

    private function persistOrder(string $date): void
    {
        $this->entityManager->persist(new Order('Customer', '10.00', new \DateTimeImmutable($date)));
    }
}
