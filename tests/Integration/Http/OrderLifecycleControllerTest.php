<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Application\Order\Command\Checkout\CheckoutHandlerEntryPoint;
use App\Application\Order\Command\Checkout\CheckoutInput;
use App\Application\Order\Command\Checkout\CheckoutItemInput;
use App\Application\Order\Command\Checkout\CheckoutPaymentInput;
use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\Order\Order;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\Enum\UserRole;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class OrderLifecycleControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($this->entityManager);
        $schema->dropDatabase();
        if ($metadata !== []) $schema->createSchema($metadata);
    }

    public function testUserCannotCancelOrder(): void
    {
        $user = new User('order-cancel-user-'.bin2hex(random_bytes(4)));
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);
        $csrf = $this->csrfToken();
        $this->client->request('POST', '/app/orders/1/cancel', [ ], [ ], [
            'HTTP_X_CSRF_TOKEN' => $csrf,
            'HTTP_IDEMPOTENCY_KEY' => 'permission-test',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['reason' => 'not allowed'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminCanCancelOrder(): void
    {
        [$admin, $orderId] = $this->createCompletedOrder();
        $this->client->loginUser($admin);
        $csrf = $this->csrfToken();
        $this->client->request('POST', '/app/orders/'.$orderId.'/cancel', [], [], [
            'HTTP_X_CSRF_TOKEN' => $csrf,
            'HTTP_IDEMPOTENCY_KEY' => 'http-cancel-'.bin2hex(random_bytes(8)),
            'HTTP_X_REQUEST_ID' => 'cancel-http-test',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['reason' => 'Customer requested cancellation'], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $body = $this->body();
        self::assertSame('CANCELLED', $body['data']['status']);
        self::assertSame('cancel-http-test', $body['requestId']);
    }

    public function testAdminCanRefundOrder(): void
    {
        [$admin, $orderId] = $this->createCompletedOrder();
        $this->client->loginUser($admin);
        $csrf = $this->csrfToken();
        $this->client->request('POST', '/app/orders/'.$orderId.'/refund', [], [], [
            'HTTP_X_CSRF_TOKEN' => $csrf,
            'HTTP_IDEMPOTENCY_KEY' => 'http-refund-'.bin2hex(random_bytes(8)),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['reason' => 'Customer returned goods'], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        self::assertSame('REFUNDED', $this->body()['data']['status']);
    }

    public function testMissingReasonIsRejected(): void
    {
        $user = new User('order-reason-'.bin2hex(random_bytes(4)));
        $user->setRoles([UserRole::ADMIN->value]);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);
        $csrf = $this->csrfToken();
        $this->client->request('POST', '/app/orders/1/cancel', [], [], [
            'HTTP_X_CSRF_TOKEN' => $csrf,
            'HTTP_IDEMPOTENCY_KEY' => 'missing-reason',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode([], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('VALIDATION_ERROR', $this->body()['errorCode']);
    }

    /** @return array{0: User, 1: int} */
    private function createCompletedOrder(): array
    {
        $admin = new User('order-admin-'.bin2hex(random_bytes(4)));
        $admin->setRoles([UserRole::ADMIN->value]);
        $product = new Product('HTTP lifecycle product', Money::fromDecimal('50000.00'));
        $product->setStockQuantityForAdjustment(3);
        $this->entityManager->persist($admin);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $provider = static::getContainer()->get(RuntimeActorContextProvider::class);
        $provider->set(new ActorContext($admin->getId(), null, 'fixture'));
        try {
            $result = static::getContainer()->get(CheckoutHandlerEntryPoint::class)->handle(new CheckoutInput(
                [new CheckoutItemInput($product->getId(), 1)],
                null,
                new CheckoutPaymentInput(PaymentMethod::CASH, Money::fromDecimal('50000.00')),
                null,
                'fixture-'.bin2hex(random_bytes(8)),
            ));
        } finally {
            $provider->clear();
        }
        return [$admin, $result->orderId];
    }

    private function csrfToken(): string
    {
        // CsrfTokenManager uses the current RequestStack session storage.
        // loginUser() authenticates the browser, but no request has yet
        // established the session in the RequestStack. Bootstrap one by
        // requesting the authenticated POS page, which also uses the same
        // session-backed CSRF storage.
        $this->client->request('GET', '/app/pos');
        self::assertResponseIsSuccessful();

        // KernelBrowser's request has the authenticated session, but the
        // RequestStack is empty again after the request completes. Re-push a
        // lightweight request carrying that exact session so the session-backed
        // CSRF token manager reads/writes the same session cookie used by the
        // following POST.
        $session = $this->client->getRequest()->getSession();
        $requestStack = static::getContainer()->get(RequestStack::class);
        $request = Request::create('/app/pos');
        $request->setSession($session);
        $requestStack->push($request);
        try {
            $token = static::getContainer()
                ->get(CsrfTokenManagerInterface::class)
                ->getToken('order_lifecycle')
                ->getValue();

            // The token is stored in the session. Persist the modified session
            // before the artificial request is popped so the next BrowserKit
            // request, which reloads the session from the cookie, sees it.
            $session->save();

            return $token;
        } finally {
            $requestStack->pop();
        }
    }

    /** @return array<string,mixed> */
    private function body(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertNotFalse($content);
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
