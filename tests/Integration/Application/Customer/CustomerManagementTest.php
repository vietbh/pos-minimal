<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Customer;

use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Customer\Command\CreateCustomer\CreateCustomerHandler;
use App\Application\Customer\Command\CreateCustomer\CreateCustomerInput;
use App\Application\Customer\Command\UpdateCustomer\UpdateCustomerHandler;
use App\Application\Customer\Command\UpdateCustomer\UpdateCustomerInput;
use App\Domain\Customer\Customer;
use App\Domain\Customer\Repository\CustomerRepositoryInterface;
use App\Tests\Integration\IntegrationTestCase;

final class CustomerManagementTest extends IntegrationTestCase
{
    public function testCreateCustomerPersistsCompleteState(): void
    {
        $handler = $this->createCustomerHandler();

        $customerId = $handler(
            new CreateCustomerInput(
                name: '  Nguyen Van A  ',
                phone: ' 0901234567 ',
                note: '  VIP customer  ',
            ),
        );

        self::assertGreaterThan(0, $customerId);

        $this->entityManager->clear();

        $customer = $this->getCustomer($customerId);

        self::assertSame('Nguyen Van A', $customer->getName());
        self::assertSame('0901234567', $customer->getPhone());
        self::assertSame('VIP customer', $customer->getNote());
    }

    public function testCreateCustomerAllowsMissingPhoneAndNote(): void
    {
        $handler = $this->createCustomerHandler();

        $customerId = $handler(
            new CreateCustomerInput(
                name: 'Walk-in Customer',
            ),
        );

        $this->entityManager->clear();

        $customer = $this->getCustomer($customerId);

        self::assertNull($customer->getPhone());
        self::assertNull($customer->getNote());
    }

    public function testCreateCustomerNormalizesBlankPhoneAndNote(): void
    {
        $handler = $this->createCustomerHandler();

        $customerId = $handler(
            new CreateCustomerInput(
                name: 'Blank Fields',
                phone: '   ',
                note: '   ',
            ),
        );

        $this->entityManager->clear();

        $customer = $this->getCustomer($customerId);

        self::assertNull($customer->getPhone());
        self::assertNull($customer->getNote());
    }

    public function testCreateCustomerRejectsDuplicatePhone(): void
    {
        $handler = $this->createCustomerHandler();

        $handler(
            new CreateCustomerInput(
                name: 'First',
                phone: '0900000001',
            ),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'A customer with this phone already exists.',
        );

        $handler(
            new CreateCustomerInput(
                name: 'Second',
                phone: '0900000001',
            ),
        );
    }

    public function testCreateCustomerRejectsInvalidName(): void
    {
        $handler = $this->createCustomerHandler();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Customer name cannot be empty.',
        );

        $handler(
            new CreateCustomerInput(
                name: '   ',
            ),
        );
    }

    public function testCreateCustomerRejectsInvalidPhoneLength(): void
    {
        $handler = $this->createCustomerHandler();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Customer phone cannot exceed 30 characters.',
        );

        $handler(
            new CreateCustomerInput(
                name: 'Too Long Phone',
                phone: str_repeat('1', 31),
            ),
        );
    }

    public function testUpdateCustomerChangesEditableFields(): void
    {
        $create = $this->createCustomerHandler();
        $update = $this->updateCustomerHandler();

        $customerId = $create(
            new CreateCustomerInput(
                name: 'Old Name',
                phone: '0900000002',
                note: 'Old note',
            ),
        );

        $update(
            new UpdateCustomerInput(
                customerId: $customerId,
                name: '  New Name ',
                phone: ' 0900000003 ',
                note: ' New note ',
            ),
        );

        $this->entityManager->clear();

        $customer = $this->getCustomer($customerId);

        self::assertSame('New Name', $customer->getName());
        self::assertSame('0900000003', $customer->getPhone());
        self::assertSame('New note', $customer->getNote());
    }

    public function testUpdateCustomerCanClearNullableFields(): void
    {
        $create = $this->createCustomerHandler();
        $update = $this->updateCustomerHandler();

        $customerId = $create(
            new CreateCustomerInput(
                name: 'Customer',
                phone: '0900000004',
                note: 'Note',
            ),
        );

        $update(
            new UpdateCustomerInput(
                customerId: $customerId,
                name: 'Customer',
                phone: null,
                note: null,
            ),
        );

        $this->entityManager->clear();

        $customer = $this->getCustomer($customerId);

        self::assertNull($customer->getPhone());
        self::assertNull($customer->getNote());
    }

    public function testUpdateCustomerRejectsDuplicatePhone(): void
    {
        $create = $this->createCustomerHandler();
        $update = $this->updateCustomerHandler();

        $create(
            new CreateCustomerInput(
                name: 'First',
                phone: '0900000005',
            ),
        );

        $secondId = $create(
            new CreateCustomerInput(
                name: 'Second',
                phone: '0900000006',
            ),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'A customer with this phone already exists.',
        );

        $update(
            new UpdateCustomerInput(
                customerId: $secondId,
                name: 'Second',
                phone: '0900000005',
            ),
        );
    }

    public function testUpdateCustomerCanKeepItsOwnPhone(): void
    {
        $create = $this->createCustomerHandler();
        $update = $this->updateCustomerHandler();

        $customerId = $create(
            new CreateCustomerInput(
                name: 'Customer',
                phone: '0900000007',
            ),
        );

        $update(
            new UpdateCustomerInput(
                customerId: $customerId,
                name: 'Renamed Customer',
                phone: '0900000007',
            ),
        );

        $this->entityManager->clear();

        $customer = $this->getCustomer($customerId);

        self::assertSame('Renamed Customer', $customer->getName());
        self::assertSame('0900000007', $customer->getPhone());
    }

    public function testUpdateCustomerRejectsUnknownCustomer(): void
    {
        $update = $this->updateCustomerHandler();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Customer 999999 was not found.',
        );

        $update(
            new UpdateCustomerInput(
                customerId: 999999,
                name: 'Unknown',
            ),
        );
    }

    public function testUpdateCustomerRejectsInvalidCustomerId(): void
    {
        $update = $this->updateCustomerHandler();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Customer ID must be greater than zero.',
        );

        $update(
            new UpdateCustomerInput(
                customerId: 0,
                name: 'Invalid',
            ),
        );
    }

    public function testUpdateCustomerRejectsInvalidName(): void
    {
        $update = $this->updateCustomerHandler();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Customer name cannot be empty.',
        );

        $update(
            new UpdateCustomerInput(
                customerId: 1,
                name: '   ',
            ),
        );
    }

    private function getCustomer(int $customerId): Customer
    {
        /** @var Customer|null $customer */
        $customer = $this->entityManager
            ->getRepository(Customer::class)
            ->find($customerId);

        self::assertNotNull($customer);

        return $customer;
    }

    private function createCustomerHandler(): CreateCustomerHandler
    {
        return new CreateCustomerHandler(
            self::getContainer()->get(
                TransactionManagerInterface::class,
            ),
            self::getContainer()->get(
                CustomerRepositoryInterface::class,
            ),
        );
    }

    private function updateCustomerHandler(): UpdateCustomerHandler
    {
        return new UpdateCustomerHandler(
            self::getContainer()->get(
                TransactionManagerInterface::class,
            ),
            self::getContainer()->get(
                CustomerRepositoryInterface::class,
            ),
        );
    }
}
