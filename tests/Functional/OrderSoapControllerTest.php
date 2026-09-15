<?php

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrderSoapControllerTest extends WebTestCase
{
    private const CREATE_ORDER_ENVELOPE = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="urn:OrderService" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"><SOAP-ENV:Body><ns1:createOrder><customerName xsi:type="xsd:string">Test Customer</customerName><amount xsi:type="xsd:string">15.50</amount><createdAt xsi:type="xsd:string">2026-01-01T00:00:00+00:00</createdAt></ns1:createOrder></SOAP-ENV:Body></SOAP-ENV:Envelope>
        XML;

    public function testWsdlIsServedOnGet(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/soap/orders');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<definitions', $client->getResponse()->getContent());
        self::assertStringContainsString('createOrder', $client->getResponse()->getContent());
    }

    public function testCreateOrderViaSoapRequest(): void
    {
        $client = static::createClient();
        self::getContainer()->get(EntityManagerInterface::class)
            ->getConnection()
            ->executeStatement('TRUNCATE orders RESTART IDENTITY');

        $client->request(
            'POST',
            '/api/soap/orders',
            server: ['CONTENT_TYPE' => 'text/xml'],
            content: self::CREATE_ORDER_ENVELOPE
        );

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('createOrderResponse', $content);
        self::assertStringContainsString('<id', $content);
    }
}
