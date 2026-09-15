<?php

namespace App\Soap;

use App\Entity\Order;
use App\Search\OrderSearchIndex;
use Doctrine\ORM\EntityManagerInterface;

class OrderSoapService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderSearchIndex $searchIndex,
    ) {
    }

    public function createOrder(string $customerName, string $amount, string $createdAt = ''): int
    {
        $customerName = trim($customerName);

        if ('' === $customerName) {
            throw new \SoapFault('Client', 'customerName is required');
        }

        if (!is_numeric($amount) || (float) $amount < 0) {
            throw new \SoapFault('Client', 'amount must be a non-negative number');
        }

        try {
            $createdAtDate = '' !== $createdAt ? new \DateTimeImmutable($createdAt) : new \DateTimeImmutable();
        } catch (\Exception) {
            throw new \SoapFault('Client', 'createdAt must be a valid date');
        }

        $order = new Order($customerName, $amount, $createdAtDate);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        try {
            $this->searchIndex->index($order);
        } catch (\Throwable) {
        }

        return $order->getId();
    }
}
