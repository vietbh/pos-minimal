<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Order\Draft;

use App\Application\Order\Command\AttachCustomerToDraft\AttachCustomerToDraftHandler;
use App\Application\Order\Command\AttachCustomerToDraft\AttachCustomerToDraftInput;
use App\Application\Order\Command\DetachCustomerFromDraft\DetachCustomerFromDraftHandler;
use App\Application\Order\Command\DetachCustomerFromDraft\DetachCustomerFromDraftInput;
use App\Domain\Customer\Customer;
use App\Domain\Order\Order;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CustomerAttachmentTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(
            EntityManagerInterface::class,
        );
    }

    public function testAttachesCustomerToDraft(): void
    {
        [$orderId, $customerId] = $this->createDraftWithCustomer();

        /** @var AttachCustomerToDraftHandler $handler */
        $handler = self::getContainer()->get(
            AttachCustomerToDraftHandler::class,
        );

        $result = $handler(
            new AttachCustomerToDraftInput(
                orderId: $orderId,
                customerId: $customerId,
            ),
        );

        self::assertSame($orderId, $result->orderId);
        self::assertSame($customerId, $result->customerId);
        self::assertSame('Nguyen Van A', $result->customerName);
        self::assertSame('0900000001', $result->customerPhone);

        $this->entityManager->clear();

        /** @var Order|null $order */
        $order = $this->entityManager->find(
            Order::class,
            $orderId,
        );

        self::assertNotNull($order);
        self::assertNotNull($order->getCustomer());
        self::assertSame(
            $customerId,
            $order->getCustomer()->getId(),
        );
    }

    public function testCanReplaceExistingCustomer(): void
    {
        [$orderId, $firstCustomerId] = $this->createDraftWithCustomer();

        $secondCustomer = new Customer(
            'Nguyen Van B',
            '0900000002',
        );

        $this->entityManager->persist($secondCustomer);
        $this->entityManager->flush();

        $secondCustomerId = $secondCustomer->getId();

        self::assertNotNull($secondCustomerId);

        /** @var AttachCustomerToDraftHandler $handler */
        $handler = self::getContainer()->get(
            AttachCustomerToDraftHandler::class,
        );

        $handler(
            new AttachCustomerToDraftInput(
                orderId: $orderId,
                customerId: $secondCustomerId,
            ),
        );

        $this->entityManager->clear();

        /** @var Order|null $order */
        $order = $this->entityManager->find(
            Order::class,
            $orderId,
        );

        self::assertNotNull($order);
        self::assertNotNull($order->getCustomer());
        self::assertSame(
            $secondCustomerId,
            $order->getCustomer()->getId(),
        );
        self::assertNotSame(
            $firstCustomerId,
            $order->getCustomer()->getId(),
        );
    }

    public function testDetachesCustomerFromDraft(): void
    {
        [$orderId] = $this->createDraftWithCustomer();

        /** @var DetachCustomerFromDraftHandler $handler */
        $handler = self::getContainer()->get(
            DetachCustomerFromDraftHandler::class,
        );

        $result = $handler(
            new DetachCustomerFromDraftInput(
                orderId: $orderId,
            ),
        );

        self::assertTrue($result->customerDetached);

        $this->entityManager->clear();

        /** @var Order|null $order */
        $order = $this->entityManager->find(
            Order::class,
            $orderId,
        );

        self::assertNotNull($order);
        self::assertNull($order->getCustomer());
    }

    public function testDetachingAlreadyDetachedDraftIsAllowed(): void
    {
        [$orderId] = $this->createDraftWithoutCustomer();

        /** @var DetachCustomerFromDraftHandler $handler */
        $handler = self::getContainer()->get(
            DetachCustomerFromDraftHandler::class,
        );

        $result = $handler(
            new DetachCustomerFromDraftInput(
                orderId: $orderId,
            ),
        );

        self::assertTrue($result->customerDetached);
    }

    public function testRejectsUnknownCustomer(): void
    {
        [$orderId] = $this->createDraftWithoutCustomer();

        /** @var AttachCustomerToDraftHandler $handler */
        $handler = self::getContainer()->get(
            AttachCustomerToDraftHandler::class,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Customer 999999999 was not found.',
        );

        $handler(
            new AttachCustomerToDraftInput(
                orderId: $orderId,
                customerId: 999999999,
            ),
        );
    }

    public function testRejectsUnknownOrderWhenAttaching(): void
    {
        /** @var AttachCustomerToDraftHandler $handler */
        $handler = self::getContainer()->get(
            AttachCustomerToDraftHandler::class,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Order 999999999 was not found.',
        );

        $handler(
            new AttachCustomerToDraftInput(
                orderId: 999999999,
                customerId: 1,
            ),
        );
    }

    public function testRejectsUnknownOrderWhenDetaching(): void
    {
        /** @var DetachCustomerFromDraftHandler $handler */
        $handler = self::getContainer()->get(
            DetachCustomerFromDraftHandler::class,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Order 999999999 was not found.',
        );

        $handler(
            new DetachCustomerFromDraftInput(
                orderId: 999999999,
            ),
        );
    }

    public function testRejectsInvalidIds(): void
    {
        /** @var AttachCustomerToDraftHandler $attach */
        $attach = self::getContainer()->get(
            AttachCustomerToDraftHandler::class,
        );

        $this->expectException(\InvalidArgumentException::class);

        $attach(
            new AttachCustomerToDraftInput(
                orderId: 0,
                customerId: 1,
            ),
        );
    }

    public function testRejectsCustomerChangeAfterCompletion(): void
    {
        [$orderId, $customerId] = $this->createDraftWithCustomer(
            withItem: true,
        );

        /** @var Order|null $order */
        $order = $this->entityManager->find(
            Order::class,
            $orderId,
        );

        self::assertNotNull($order);
        self::assertCount(1, $order->getItems());

        $order->complete();

        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var AttachCustomerToDraftHandler $handler */
        $handler = self::getContainer()->get(
            AttachCustomerToDraftHandler::class,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Customer can only be changed while order is draft.',
        );

        $handler(
            new AttachCustomerToDraftInput(
                orderId: $orderId,
                customerId: $customerId,
            ),
        );
    }

    private function createDraftWithCustomer(
        bool $withItem = false,
    ): array {
        $user = new User(
            'customer-attach-'.bin2hex(random_bytes(6)),
        );

        $customer = new Customer(
            'Nguyen Van A',
            '0900000001',
        );

        $order = new Order(
            orderNumber: new OrderNumber(
                'ORD-CUSTOMER-'.strtoupper(
                    bin2hex(random_bytes(5)),
                ),
            ),
            user: $user,
        );

        if ($withItem) {
            $product = new Product(
                'Customer Attachment Product',
                Money::fromDecimal('10000.00'),
            );

            $item = new \App\Domain\Order\OrderItem(
                product: $product,
                quantity: 1,
                unitPrice: $product->getSellingPrice(),
            );

            $order->addItem($item);

            $this->entityManager->persist($product);
        }

        $this->entityManager->persist($user);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($order);

        $this->entityManager->flush();

        $orderId = $order->getId();
        $customerId = $customer->getId();

        self::assertNotNull($orderId);
        self::assertNotNull($customerId);

        return [$orderId, $customerId];
    }

    private function createDraftWithoutCustomer(): array
    {
        $user = new User(
            'customer-detach-'.bin2hex(random_bytes(6)),
        );

        $order = new Order(
            orderNumber: new OrderNumber(
                'ORD-DETACH-'.strtoupper(
                    bin2hex(random_bytes(5)),
                ),
            ),
            user: $user,
        );

        $this->entityManager->persist($user);
        $this->entityManager->persist($order);

        $this->entityManager->flush();

        $orderId = $order->getId();

        self::assertNotNull($orderId);

        return [$orderId];
    }
}
