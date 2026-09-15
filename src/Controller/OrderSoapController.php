<?php

namespace App\Controller;

use App\Soap\OrderSoapService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OrderSoapController
{
    public function __construct(
        private readonly OrderSoapService $orderSoapService,
        private readonly string $cacheDir,
    ) {
    }

    #[Route('/api/soap/orders', name: 'api_soap_orders', methods: ['GET', 'POST'])]
    #[OA\Get(
        path: '/api/soap/orders',
        summary: 'WSDL describing the SOAP order service',
        responses: [
            new OA\Response(
                response: 200,
                description: 'WSDL document',
                content: new OA\MediaType(mediaType: 'text/xml')
            ),
        ]
    )]
    #[OA\Post(
        path: '/api/soap/orders',
        summary: 'SOAP endpoint to create an order',
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'text/xml',
                example: <<<XML
                    <?xml version="1.0" encoding="UTF-8"?>
                    <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
                      <SOAP-ENV:Body>
                        <ns1:createOrder xmlns:ns1="urn:OrderService" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
                          <customerName xsi:type="xsd:string">John Doe</customerName>
                          <amount xsi:type="xsd:string">100.50</amount>
                          <createdAt xsi:type="xsd:string">2026-09-15</createdAt>
                        </ns1:createOrder>
                      </SOAP-ENV:Body>
                    </SOAP-ENV:Envelope>
                    XML
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'SOAP envelope with the created order id, or a SOAP Fault'),
        ]
    )]
    public function __invoke(Request $request): Response
    {
        $wsdl = $this->buildWsdl($request->getSchemeAndHttpHost().'/api/soap/orders');

        if ($request->isMethod('GET')) {
            return new Response($wsdl, Response::HTTP_OK, ['Content-Type' => 'text/xml']);
        }

        $wsdlPath = $this->cacheDir.'/soap-orders.wsdl';
        file_put_contents($wsdlPath, $wsdl);

        $server = new \SoapServer($wsdlPath, ['cache_wsdl' => WSDL_CACHE_NONE]);
        $server->setObject($this->orderSoapService);

        ob_start();
        $server->handle($request->getContent());
        $responseBody = ob_get_clean();

        return new Response($responseBody, Response::HTTP_OK, ['Content-Type' => 'text/xml']);
    }

    private function buildWsdl(string $location): string
    {
        return <<<WSDL
            <?xml version="1.0" encoding="UTF-8"?>
            <definitions name="OrderService"
                targetNamespace="urn:OrderService"
                xmlns:tns="urn:OrderService"
                xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
                xmlns="http://schemas.xmlsoap.org/wsdl/">

                <message name="createOrderRequest">
                    <part name="customerName" type="xsd:string"/>
                    <part name="amount" type="xsd:string"/>
                    <part name="createdAt" type="xsd:string"/>
                </message>
                <message name="createOrderResponse">
                    <part name="id" type="xsd:int"/>
                </message>

                <portType name="OrderServicePortType">
                    <operation name="createOrder">
                        <input message="tns:createOrderRequest"/>
                        <output message="tns:createOrderResponse"/>
                    </operation>
                </portType>

                <binding name="OrderServiceBinding" type="tns:OrderServicePortType">
                    <soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http"/>
                    <operation name="createOrder">
                        <soap:operation soapAction="urn:OrderService#createOrder"/>
                        <input>
                            <soap:body use="encoded" namespace="urn:OrderService" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/>
                        </input>
                        <output>
                            <soap:body use="encoded" namespace="urn:OrderService" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/>
                        </output>
                    </operation>
                </binding>

                <service name="OrderService">
                    <port name="OrderServicePort" binding="tns:OrderServiceBinding">
                        <soap:address location="{$location}"/>
                    </port>
                </service>
            </definitions>
            WSDL;
    }
}
