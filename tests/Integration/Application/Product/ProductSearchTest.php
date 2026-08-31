<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Product;

use App\Application\Product\Query\SearchProducts\ProductSearchResult;
use App\Application\Product\Query\SearchProducts\SearchProductsHandler;
use App\Application\Product\Query\SearchProducts\SearchProductsInput;
use App\Infrastructure\Persistence\Doctrine\Query\ProductQueryRepository;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class ProductSearchTest extends IntegrationTestCase
{
    private SearchProductsHandler $searchHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchHandler = new SearchProductsHandler(
            new ProductQueryRepository(
                self::getContainer()->get(EntityManagerInterface::class),
            ),
        );
    }

    public function testSearchByPartialName(): void
    {
        $this->createProduct(
            'Coca Cola',
            '8931111111111',
            15000,
        );

        $this->createProduct(
            'Pepsi Cola',
            '8932222222222',
            12000,
        );

        $this->createProduct(
            'Mineral Water',
            '8933333333333',
            8000,
        );

        $results = ($this->searchHandler)(
            new SearchProductsInput('coca'),
        );

        self::assertCount(1, $results);
        self::assertSame('Coca Cola', $results[0]->name);
    }

    public function testSearchByPartialSku(): void
    {
        $this->createProduct(
            'Coca Cola',
            '8931111111111',
            15000,
        );

        $this->createProduct(
            'Pepsi Cola',
            '8932222222222',
            12000,
        );

        $results = ($this->searchHandler)(
            new SearchProductsInput('2222'),
        );

        self::assertCount(1, $results);
        self::assertSame('Pepsi Cola', $results[0]->name);
        self::assertSame('8932222222222', $results[0]->sku);
    }

    public function testSearchMatchesNameOrSku(): void
    {
        $this->createProduct(
            'Coca Cola',
            '8931111111111',
            15000,
        );

        $this->createProduct(
            'Pepsi Cola',
            '8932222222222',
            12000,
        );

        $byName = ($this->searchHandler)(
            new SearchProductsInput('coca'),
        );

        $bySku = ($this->searchHandler)(
            new SearchProductsInput('2222'),
        );

        self::assertSame('Coca Cola', $byName[0]->name);
        self::assertSame('Pepsi Cola', $bySku[0]->name);
    }

    public function testSearchTrimsQuery(): void
    {
        $this->createProduct(
            'Coca Cola',
            '8931111111111',
            15000,
        );

        $results = ($this->searchHandler)(
            new SearchProductsInput('  coca  '),
        );

        self::assertCount(1, $results);
        self::assertSame('Coca Cola', $results[0]->name);
    }

    public function testBlankQueryReturnsEmptyResult(): void
    {
        $this->createProduct(
            'Coca Cola',
            '8931111111111',
            15000,
        );

        $results = ($this->searchHandler)(
            new SearchProductsInput('   '),
        );

        self::assertSame([], $results);
    }

    public function testUnknownQueryReturnsEmptyResult(): void
    {
        $this->createProduct(
            'Coca Cola',
            '8931111111111',
            15000,
        );

        $results = ($this->searchHandler)(
            new SearchProductsInput('unknown-product'),
        );

        self::assertSame([], $results);
    }

    public function testSearchReturnsOnlyActiveProducts(): void
    {
        $this->createProduct(
            'Active Cola',
            '8931111111111',
            15000,
        );

        $inactive = $this->createProduct(
            'Inactive Cola',
            '8932222222222',
            12000,
        );

        $inactive->deactivate();

        $this->flush();

        $results = ($this->searchHandler)(
            new SearchProductsInput('cola'),
        );

        self::assertCount(1, $results);
        self::assertSame('Active Cola', $results[0]->name);
    }

    public function testSearchIsBoundedByLimit(): void
    {
        $this->createProduct('Cola A', 'SKU-A', 10000);
        $this->createProduct('Cola B', 'SKU-B', 11000);
        $this->createProduct('Cola C', 'SKU-C', 12000);

        $results = ($this->searchHandler)(
            new SearchProductsInput('cola', 2),
        );

        self::assertCount(2, $results);
    }

    public function testSearchUsesDeterministicOrdering(): void
    {
        $first = $this->createProduct(
            'Same Product',
            'SKU-A',
            10000,
        );

        $second = $this->createProduct(
            'Same Product',
            'SKU-B',
            11000,
        );

        $results = ($this->searchHandler)(
            new SearchProductsInput('same product'),
        );

        self::assertCount(2, $results);
        self::assertSame(
            $first->getId(),
            $results[0]->id,
        );
        self::assertSame(
            $second->getId(),
            $results[1]->id,
        );
    }

    public function testSearchReturnsOnlyPosFields(): void
    {
        $this->createProduct(
            'Coca Cola',
            '8931111111111',
            15000,
        );

        $results = ($this->searchHandler)(
            new SearchProductsInput('coca'),
        );

        self::assertCount(1, $results);
        self::assertInstanceOf(
            ProductSearchResult::class,
            $results[0],
        );

        self::assertSame(
            [
                'id',
                'sku',
                'name',
                'unit',
                'sellingPrice',
                'stockQuantity',
            ],
            array_keys(get_object_vars($results[0])),
        );
    }

    public function testNonPositiveLimitIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SearchProductsInput('coca', 0);
    }

    public function testLookupByExactSkuReturnsActiveProduct(): void
    {
        $this->createProduct(
            'Coca Cola',
            '8931111111111',
            15000,
        );

        $repository = new ProductQueryRepository(
            self::getContainer()->get(EntityManagerInterface::class),
        );

        $result = $repository->findActiveBySku(
            ' 8931111111111 ',
        );

        self::assertNotNull($result);
        self::assertSame('Coca Cola', $result->name);
        self::assertSame('8931111111111', $result->sku);
    }

    public function testLookupByUnknownSkuReturnsNull(): void
    {
        $repository = new ProductQueryRepository(
            self::getContainer()->get(EntityManagerInterface::class),
        );

        self::assertNull(
            $repository->findActiveBySku('UNKNOWN-SKU'),
        );
    }

    public function testLookupBySkuDoesNotReturnInactiveProduct(): void
    {
        $product = $this->createProduct(
            'Inactive Cola',
            '8931111111111',
            15000,
        );

        $product->deactivate();
        $this->flush();

        $repository = new ProductQueryRepository(
            self::getContainer()->get(EntityManagerInterface::class),
        );

        self::assertNull(
            $repository->findActiveBySku('8931111111111'),
        );
    }

    private function createProduct(
        string $name,
        ?string $sku,
        int $sellingPrice,
    ): \App\Domain\Product\Product {
        $product = new \App\Domain\Product\Product(
            name: $name,
            sellingPrice: \App\Domain\Shared\ValueObject\Money::fromInt(
                $sellingPrice,
            ),
            sku: $sku === null
                ? null
                : new \App\Domain\Product\ValueObject\Sku($sku),
        );

        $entityManager = self::getContainer()->get(
            EntityManagerInterface::class,
        );

        $entityManager->persist($product);
        $entityManager->flush();

        return $product;
    }

    private function flush(): void
    {
        self::getContainer()
            ->get(EntityManagerInterface::class)
            ->flush();
    }
}
