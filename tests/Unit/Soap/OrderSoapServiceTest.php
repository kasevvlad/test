<?php

namespace App\Tests\Unit\Soap;

use App\Soap\OrderSoapService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderSoapServiceTest extends KernelTestCase
{
    private OrderSoapService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(OrderSoapService::class);
        self::getContainer()->get(EntityManagerInterface::class)
            ->getConnection()
            ->executeStatement('TRUNCATE orders RESTART IDENTITY');
    }

    public function testCreateOrderPersistsAndReturnsId(): void
    {
        $id = $this->service->createOrder('Test Customer', '15.50', '2026-01-01T00:00:00+00:00');

        self::assertSame(1, $id);
    }

    public function testCreateOrderDefaultsCreatedAtToNow(): void
    {
        $id = $this->service->createOrder('Test Customer', '15.50');

        self::assertGreaterThan(0, $id);
    }

    public function testCreateOrderRejectsEmptyCustomerName(): void
    {
        $this->expectException(\SoapFault::class);

        $this->service->createOrder('   ', '15.50');
    }

    public function testCreateOrderRejectsInvalidAmount(): void
    {
        $this->expectException(\SoapFault::class);

        $this->service->createOrder('Test Customer', 'not-a-number');
    }

    public function testCreateOrderRejectsInvalidCreatedAt(): void
    {
        $this->expectException(\SoapFault::class);

        $this->service->createOrder('Test Customer', '15.50', 'not-a-date');
    }
}
