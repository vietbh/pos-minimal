<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Customer;

use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Customer\Command\CreateCustomer\CreateCustomerHandler;
use App\Application\Customer\Command\CreateCustomer\CreateCustomerInput;
use App\Application\Customer\Query\SearchCustomers\CustomerSearchResult;
use App\Application\Customer\Query\SearchCustomers\SearchCustomersHandler;
use App\Application\Customer\Query\SearchCustomers\SearchCustomersInput;
use App\Domain\Customer\Customer;
use App\Tests\Integration\IntegrationTestCase;

final class CustomerSearchTest extends IntegrationTestCase
{
    public function testSearchByPartialName(): void
    {
        $this->createCustomer('Nguyen Van An', '0901000001');
        $this->createCustomer('Nguyen Van Binh', '0901000002');
        $this->createCustomer('Tran Van An', '0901000003');

        $results = $this->search('Nguyen');

        self::assertCount(2, $results);

        self::assertSame(
            'Nguyen Van An',
            $results[0]->name,
        );

        self::assertSame(
            'Nguyen Van Binh',
            $results[1]->name,
        );
    }

    public function testSearchByPartialPhone(): void
    {
        $this->createCustomer('Customer A', '0901234567');
        $this->createCustomer('Customer B', '0912345678');
        $this->createCustomer('Customer C', '0987654321');

        $results = $this->search('1234');

        self::assertCount(2, $results);

        self::assertSame(
            '0901234567',
            $results[0]->phone,
        );

        self::assertSame(
            '0912345678',
            $results[1]->phone,
        );
    }

    public function testSearchMatchesNameOrPhone(): void
    {
        $this->createCustomer('Nguyen Search', '0909999991');
        $this->createCustomer('Phone Search', '0901234567');
        $this->createCustomer('Other Customer', '0911111111');

        $byName = $this->search('Nguyen');

        self::assertCount(1, $byName);
        self::assertSame(
            'Nguyen Search',
            $byName[0]->name,
        );

        $byPhone = $this->search('1234567');

        self::assertCount(1, $byPhone);
        self::assertSame(
            'Phone Search',
            $byPhone[0]->name,
        );
    }

    public function testSearchTrimsQuery(): void
    {
        $this->createCustomer(
            'Nguyen Trimmed',
            '0902000001',
        );

        $results = $this->search('  Nguyen Trimmed  ');

        self::assertCount(1, $results);
        self::assertSame(
            'Nguyen Trimmed',
            $results[0]->name,
        );
    }

    public function testBlankQueryReturnsEmptyResult(): void
    {
        $this->createCustomer(
            'Existing Customer',
            '0903000001',
        );

        $results = $this->search('   ');

        self::assertSame([], $results);
    }

    public function testUnknownQueryReturnsEmptyResult(): void
    {
        $this->createCustomer(
            'Existing Customer',
            '0904000001',
        );

        $results = $this->search('does-not-exist');

        self::assertSame([], $results);
    }

    public function testSearchIsBoundedByLimit(): void
    {
        $this->createCustomer('Customer A', '0905000001');
        $this->createCustomer('Customer B', '0905000002');
        $this->createCustomer('Customer C', '0905000003');
        $this->createCustomer('Customer D', '0905000004');

        $results = $this->search(
            'Customer',
            2,
        );

        self::assertCount(2, $results);
    }

    public function testSearchUsesDeterministicOrdering(): void
    {
        $firstId = $this->createCustomer(
            'Same Name',
            '0906000001',
        );

        $secondId = $this->createCustomer(
            'Same Name',
            '0906000002',
        );

        $thirdId = $this->createCustomer(
            'Same Name',
            '0906000003',
        );

        $results = $this->search('Same Name');

        self::assertCount(3, $results);

        self::assertSame(
            $firstId,
            $results[0]->id,
        );

        self::assertSame(
            $secondId,
            $results[1]->id,
        );

        self::assertSame(
            $thirdId,
            $results[2]->id,
        );
    }

    public function testSearchReturnsOnlyPosFields(): void
    {
        $customerId = $this->createCustomer(
            'POS Customer',
            '0907000001',
        );

        $results = $this->search('POS Customer');

        self::assertCount(1, $results);

        $result = $results[0];

        self::assertInstanceOf(
            CustomerSearchResult::class,
            $result,
        );

        self::assertSame(
            $customerId,
            $result->id,
        );

        self::assertSame(
            'POS Customer',
            $result->name,
        );

        self::assertSame(
            '0907000001',
            $result->phone,
        );
    }

    public function testNonPositiveLimitIsRejected(): void
    {
        $handler = $this->searchHandler();

        $this->expectException(
            \InvalidArgumentException::class,
        );

        $this->expectExceptionMessage(
            'Search limit must be greater than zero.',
        );

        $handler(
            new SearchCustomersInput(
                query: 'Customer',
                limit: 0,
            ),
        );
    }

    private function search(
        string $query,
        int $limit = 20,
    ): array {
        $handler = $this->searchHandler();

        return $handler(
            new SearchCustomersInput(
                query: $query,
                limit: $limit,
            ),
        );
    }

    private function searchHandler(): SearchCustomersHandler
    {
        return new SearchCustomersHandler(
            new \App\Infrastructure\Persistence\Doctrine\Query\CustomerQueryRepository(
                $this->entityManager,
            ),
        );
    }

    private function createCustomer(
        string $name,
        ?string $phone = null,
    ): int {
        $handler = new CreateCustomerHandler(
            self::getContainer()->get(
                TransactionManagerInterface::class,
            ),
            self::getContainer()->get(
                \App\Domain\Customer\Repository\CustomerRepositoryInterface::class,
            ),
        );

        $id = $handler(
            new CreateCustomerInput(
                name: $name,
                phone: $phone,
            ),
        );

        $this->entityManager->clear();

        /** @var Customer|null $customer */
        $customer = $this->entityManager
            ->getRepository(Customer::class)
            ->find($id);

        self::assertNotNull($customer);

        return $id;
    }
}
