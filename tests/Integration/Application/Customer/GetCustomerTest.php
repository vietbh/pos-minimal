<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Customer;

use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Customer\Command\CreateCustomer\CreateCustomerHandler;
use App\Application\Customer\Command\CreateCustomer\CreateCustomerInput;
use App\Application\Customer\Query\GetCustomer\{CustomerDetailResult, GetCustomerHandler, GetCustomerInput};
use App\Domain\Customer\Customer;
use App\Tests\Integration\IntegrationTestCase;

final class GetCustomerTest extends IntegrationTestCase
{
    public function testDetailReturnsCustomerAndEmptyOperationalHistory(): void
    {
        $create = new CreateCustomerHandler(
            self::getContainer()->get(TransactionManagerInterface::class),
            self::getContainer()->get(\App\Domain\Customer\Repository\CustomerRepositoryInterface::class),
        );

        $id = $create(new CreateCustomerInput(
            name: 'Detail Customer',
            phone: '0909123456',
            note: 'Customer note',
        ));

        $this->entityManager->clear();

        $handler = new GetCustomerHandler(
            new \App\Infrastructure\Persistence\Doctrine\Query\CustomerQueryRepository($this->entityManager),
        );

        $result = $handler(new GetCustomerInput($id));

        self::assertInstanceOf(CustomerDetailResult::class, $result);
        self::assertSame($id, $result->id);
        self::assertSame('Detail Customer', $result->name);
        self::assertSame('0909123456', $result->phone);
        self::assertSame('Customer note', $result->note);
        self::assertSame(0, $result->debtCount);
        self::assertSame('0.00', $result->debtOriginalAmount);
        self::assertSame('0.00', $result->debtPaidAmount);
        self::assertSame('0.00', $result->debtRemainingAmount);
        self::assertSame([], $result->debts);
        self::assertSame([], $result->orders);
        self::assertSame(0, $result->orderCount);
    }

    public function testUnknownCustomerReturnsNull(): void
    {
        $handler = new GetCustomerHandler(
            new \App\Infrastructure\Persistence\Doctrine\Query\CustomerQueryRepository($this->entityManager),
        );

        self::assertNull($handler(new GetCustomerInput(999999)));
    }

    public function testNonPositiveIdIsRejected(): void
    {
        $handler = new GetCustomerHandler(
            new \App\Infrastructure\Persistence\Doctrine\Query\CustomerQueryRepository($this->entityManager),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer ID must be greater than zero.');

        $handler(new GetCustomerInput(0));
    }
}
