<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\Payment;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class OrderControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        if ($metadata !== []) {
            $schemaTool->createSchema($metadata);
        }
    }

    public function testOrdersRequiresAuthentication(): void
    {
        $this->client->request('GET', '/app/orders');
        self::assertResponseRedirects('/auth/login');
    }

    public function testUserCanViewOrderListAndDetail(): void
    {
        $user = new User('orders-http-'.bin2hex(random_bytes(4)));
        $product = new Product('HTTP Order Product', Money::fromDecimal('42.00'));
        $product->setStockQuantityForAdjustment(5);
        $this->entityManager->persist($user);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $order = new Order(new OrderNumber('HTTP-ORDER-1'), $user);
        $order->addItem(new OrderItem($product, 1, Money::fromDecimal('42.00')));
        $order->addPayment(new Payment(Money::fromDecimal('42.00'), PaymentMethod::CASH, $user));
        $order->complete();
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/app/orders?q=HTTP-ORDER-1');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('HTTP-ORDER-1', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/app/orders/'.$order->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('HTTP Order Product', (string) $this->client->getResponse()->getContent());
    }
    public function testUnknownOrderReturns404(): void
    {
        $user = new User('orders-404-'.bin2hex(random_bytes(4)));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($user);

        $this->client->request('GET', '/app/orders/999999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
//    public function testOrderViewPermissionIsEnforced(): void
//    {
//        $user = new User('orders-no-permission-'.bin2hex(random_bytes(4)));
//        $user->setRoles([]);
//        $this->entityManager->persist($user);
//        $this->entityManager->flush();
//
//        $this->client->loginUser($user);
//        $this->client->request('GET', '/orders');
//        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
//    }
}
