<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrderControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->executeStatement('TRUNCATE orders RESTART IDENTITY');
    }

    public function testGetExistingOrder(): void
    {
        $order = new Order('John Doe', '42.50', new \DateTimeImmutable('2026-03-01T10:00:00+00:00'));
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/orders/'.$order->getId());

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            json_encode([
                'id' => $order->getId(),
                'customerName' => 'John Doe',
                'amount' => 42.5,
                'createdAt' => '2026-03-01T10:00:00+00:00',
            ]),
            $this->client->getResponse()->getContent()
        );
    }

    public function testGetMissingOrderReturnsNotFound(): void
    {
        $this->client->request('GET', '/api/orders/999999');

        self::assertResponseStatusCodeSame(404);
    }
}
