<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Domain\Order\Order;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CheckoutControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->entityManager = static::getContainer()
            ->get(EntityManagerInterface::class);

        $metadata = $this->entityManager
            ->getMetadataFactory()
            ->getAllMetadata();

        $schemaTool = new SchemaTool($this->entityManager);

        $schemaTool->dropDatabase();

        if ($metadata !== []) {
            $schemaTool->createSchema($metadata);
        }
    }

    public function testCheckoutEndpointRequiresAuthentication(): void
    {
        $this->client->request(
            'POST',
            '/api/pos/checkout',
            server: [
                'HTTP_IDEMPOTENCY_KEY' => 'unauthenticated',
                'HTTP_X_CSRF_TOKEN' => 'invalid',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(
                ['items' => []],
                JSON_THROW_ON_ERROR
            )
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_UNAUTHORIZED
        );

        $body = $this->responseBody();

        self::assertSame(
            'AUTHENTICATION_REQUIRED',
            $body['errorCode']
        );
    }

    public function testCheckoutEndpointReturnsAuthoritativeResult(): void
    {
        $user = new User(
            'http-checkout-' . bin2hex(random_bytes(4))
        );

        $product = new Product(
            'HTTP Product',
            Money::fromDecimal('12500.00')
        );

        $product->setStockQuantityForAdjustment(5);

        $this->entityManager->persist($user);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $this->client->loginUser($user);

        $csrf = $this->getCheckoutCsrfToken();

        $this->client->request(
            'POST',
            '/api/pos/checkout',
            server: [
                'HTTP_IDEMPOTENCY_KEY' =>
                    'http-checkout-' . bin2hex(random_bytes(8)),
                'HTTP_X_CSRF_TOKEN' => $csrf,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUEST_ID' => 'http-test-request',
            ],
            content: json_encode(
                [
                    'items' => [
                        [
                            'productId' => $product->getId(),
                            'quantity' => 2,
                        ],
                    ],
                    'customerId' => null,
                    'payment' => [
                        'method' => 'CASH',
                        'amount' => '25000.00',
                    ],
                    'note' => 'http checkout',
                    'clientTotal' => '999999999.99',
                ],
                JSON_THROW_ON_ERROR
            )
        );

        self::assertResponseIsSuccessful();

        self::assertSame(
            'http-test-request',
            $this->client
                ->getResponse()
                ->headers
                ->get('X-Request-ID')
        );

        $body = $this->responseBody();

        self::assertSame(
            '25000.00',
            $body['data']['total']
        );

        self::assertSame(
            '25000.00',
            $body['data']['paidAmount']
        );

        self::assertSame(
            'COMPLETED',
            $body['data']['status']
        );

        $this->entityManager->clear();

        $order = $this->entityManager->find(
            Order::class,
            $body['data']['orderId']
        );

        self::assertNotNull($order);

        self::assertSame(
            '25000.00',
            $order->getTotal()->toDecimal()
        );
    }

    public function testMissingIdempotencyKeyIsRejected(): void
    {
        $user = new User(
            'http-no-key-' . bin2hex(random_bytes(4))
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($user);

        $csrf = $this->getCheckoutCsrfToken();

        $this->client->request(
            'POST',
            '/api/pos/checkout',
            server: [
                'HTTP_X_CSRF_TOKEN' => $csrf,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(
                ['items' => []],
                JSON_THROW_ON_ERROR
            )
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_BAD_REQUEST
        );

        $body = $this->responseBody();

        self::assertSame(
            'IDEMPOTENCY_KEY_REQUIRED',
            $body['errorCode']
        );
    }

    public function testInvalidCsrfIsRejected(): void
    {
        $user = new User(
            'http-csrf-' . bin2hex(random_bytes(4))
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($user);

        // Establish authenticated session before testing CSRF.
        $this->client->request('GET', '/pos');

        self::assertResponseIsSuccessful();

        $this->client->request(
            'POST',
            '/api/pos/checkout',
            server: [
                'HTTP_IDEMPOTENCY_KEY' => 'csrf-test',
                'HTTP_X_CSRF_TOKEN' => 'invalid',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(
                ['items' => []],
                JSON_THROW_ON_ERROR
            )
        );

        self::assertResponseStatusCodeSame(
            Response::HTTP_FORBIDDEN
        );

        $body = $this->responseBody();

        self::assertSame(
            'CSRF_INVALID',
            $body['errorCode']
        );
    }

    private function getCheckoutCsrfToken(): string
    {
        $this->client->request('GET', '/pos');

        self::assertResponseIsSuccessful();

        $html = $this->client->getResponse()->getContent();

        self::assertNotFalse($html);

        preg_match(
            '/data-pos-checkout-csrf-token-value="([^"]+)"/',
            $html,
            $matches
        );

        self::assertArrayHasKey(1, $matches);

        return html_entity_decode(
            $matches[1],
            ENT_QUOTES | ENT_HTML5
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseBody(): array
    {
        $content = $this->client
            ->getResponse()
            ->getContent();

        self::assertNotFalse($content);

        return json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
