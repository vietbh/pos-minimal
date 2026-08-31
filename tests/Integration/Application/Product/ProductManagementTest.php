<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Product;

use App\Application\Product\Command\ActivateProduct\ActivateProductHandler;
use App\Application\Product\Command\ActivateProduct\ActivateProductInput;
use App\Application\Product\Command\ChangeProductPrice\ChangeProductPriceHandler;
use App\Application\Product\Command\ChangeProductPrice\ChangeProductPriceInput;
use App\Application\Product\Command\CreateProduct\CreateProductHandler;
use App\Application\Product\Command\CreateProduct\CreateProductInput;
use App\Application\Product\Command\DeactivateProduct\DeactivateProductHandler;
use App\Application\Product\Command\DeactivateProduct\DeactivateProductInput;
use App\Application\Product\Command\UpdateProduct\UpdateProductHandler;
use App\Application\Product\Command\UpdateProduct\UpdateProductInput;
use App\Domain\Product\Product;
use App\Domain\Product\ValueObject\Sku;
use App\Domain\Shared\ValueObject\Money;
use App\Tests\Integration\IntegrationTestCase;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Product\Repository\ProductRepositoryInterface;

final class ProductManagementTest extends IntegrationTestCase
{
    public function testCreateProductPersistsCompleteState(): void
    {
        $handler = $this->createProductHandler();

        $sku = new Sku('SKU-001');

        $input = new CreateProductInput(
            name: '  Coffee  ',
            sellingPrice: Money::fromDecimal('125000.00'),
            sku: $sku,
            unit: '  box ',
            costPrice: Money::fromDecimal('80000.00'),
            lowStockThreshold: 5,
            note: '  Main product  ',
        );

        $productId = $handler($input);

        self::assertGreaterThan(0, $productId);

        $this->entityManager->clear();

        /** @var Product|null $product */
        $product = $this->entityManager
            ->getRepository(Product::class)
            ->find($productId);

        self::assertNotNull($product);

        self::assertSame('Coffee', $product->getName());
        self::assertSame('SKU-001', $product->getSku()?->value());
        self::assertSame('box', $product->getUnit());
        self::assertSame(
            '125000.00',
            $product->getSellingPrice()->toDecimal(),
        );
        self::assertSame(
            '80000.00',
            $product->getCostPrice()?->toDecimal(),
        );
        self::assertSame(0, $product->getStockQuantity());
        self::assertSame(5, $product->getLowStockThreshold());
        self::assertSame('Main product', $product->getNote());
        self::assertTrue($product->isActive());
    }

    public function testCreateProductDefaultsStockToZeroAndActiveToTrue(): void
    {
        $handler = $this->createProductHandler();

        $productId = $handler(
            new CreateProductInput(
                name: 'Default Product',
                sellingPrice: Money::fromDecimal('10000.00'),
            ),
        );

        $this->entityManager->clear();

        /** @var Product|null $product */
        $product = $this->entityManager
            ->getRepository(Product::class)
            ->find($productId);

        self::assertNotNull($product);
        self::assertSame(0, $product->getStockQuantity());
        self::assertSame(0, $product->getLowStockThreshold());
        self::assertNull($product->getSku());
        self::assertNull($product->getUnit());
        self::assertNull($product->getCostPrice());
        self::assertNull($product->getNote());
        self::assertTrue($product->isActive());
    }

    public function testCreateProductRejectsDuplicateSku(): void
    {
        $handler = $this->createProductHandler();

        $sku = new Sku('DUPLICATE-SKU');

        $handler(
            new CreateProductInput(
                name: 'First Product',
                sellingPrice: Money::fromDecimal('10000.00'),
                sku: $sku,
            ),
        );

        $this->entityManager->clear();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'A product with this SKU already exists.',
        );

        $handler(
            new CreateProductInput(
                name: 'Second Product',
                sellingPrice: Money::fromDecimal('20000.00'),
                sku: new Sku('DUPLICATE-SKU'),
            ),
        );
    }

    public function testCreateProductRejectsInvalidInput(): void
    {
        $handler = $this->createProductHandler();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Product name cannot be empty.',
        );

        $handler(
            new CreateProductInput(
                name: '   ',
                sellingPrice: Money::fromDecimal('10000.00'),
            ),
        );
    }

    public function testUpdateProductChangesEditableFieldsOnly(): void
    {
        $createHandler = $this->createProductHandler();

        $updateHandler = $this->updateProductHandler();

        $productId = $createHandler(
            new CreateProductInput(
                name: 'Original Product',
                sellingPrice: Money::fromDecimal('100000.00'),
                sku: new Sku('ORIGINAL-SKU'),
                unit: 'piece',
                costPrice: Money::fromDecimal('60000.00'),
                lowStockThreshold: 3,
                note: 'Original note',
            ),
        );

        $this->entityManager->clear();

        $product = $this->getProduct($productId);
        $product->setStockQuantityForAdjustment(25);
        $this->entityManager->flush();

        $beforeSellingPrice = $product
            ->getSellingPrice()
            ->toDecimal();

        $updateHandler(
            new UpdateProductInput(
                productId: $productId,
                name: 'Updated Product',
                sku: new Sku('UPDATED-SKU'),
                unit: 'box',
                costPrice: Money::fromDecimal('70000.00'),
                lowStockThreshold: 8,
                note: 'Updated note',
            ),
        );

        $this->entityManager->clear();

        $product = $this->getProduct($productId);

        self::assertSame('Updated Product', $product->getName());
        self::assertSame('UPDATED-SKU', $product->getSku()?->value());
        self::assertSame('box', $product->getUnit());
        self::assertSame(
            '70000.00',
            $product->getCostPrice()?->toDecimal(),
        );
        self::assertSame(8, $product->getLowStockThreshold());
        self::assertSame('Updated note', $product->getNote());

        /*
         * UpdateProduct must not mutate selling price or stock.
         */
        self::assertSame(
            $beforeSellingPrice,
            $product->getSellingPrice()->toDecimal(),
        );
        self::assertSame(25, $product->getStockQuantity());
    }

    public function testUpdateProductCanClearNullableFields(): void
    {
        $createHandler = $this->createProductHandler();

        $updateHandler = $this->updateProductHandler();

        $productId = $createHandler(
            new CreateProductInput(
                name: 'Nullable Product',
                sellingPrice: Money::fromDecimal('50000.00'),
                sku: new Sku('NULLABLE-SKU'),
                unit: 'box',
                costPrice: Money::fromDecimal('30000.00'),
                lowStockThreshold: 4,
                note: 'Some note',
            ),
        );

        $this->entityManager->clear();

        $updateHandler(
            new UpdateProductInput(
                productId: $productId,
                name: 'Nullable Product Updated',
                sku: null,
                unit: null,
                costPrice: null,
                lowStockThreshold: 0,
                note: null,
            ),
        );

        $this->entityManager->clear();

        $product = $this->getProduct($productId);

        self::assertNull($product->getSku());
        self::assertNull($product->getUnit());
        self::assertNull($product->getCostPrice());
        self::assertSame(0, $product->getLowStockThreshold());
        self::assertNull($product->getNote());
    }

    public function testUpdateProductRejectsDuplicateSku(): void
    {
        $createHandler = $this->createProductHandler();

        $updateHandler = $this->updateProductHandler();

        $firstId = $createHandler(
            new CreateProductInput(
                name: 'First Product',
                sellingPrice: Money::fromDecimal('10000.00'),
                sku: new Sku('SKU-FIRST'),
            ),
        );

        $secondId = $createHandler(
            new CreateProductInput(
                name: 'Second Product',
                sellingPrice: Money::fromDecimal('20000.00'),
                sku: new Sku('SKU-SECOND'),
            ),
        );

        self::assertNotSame($firstId, $secondId);

        $this->entityManager->clear();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'A product with this SKU already exists.',
        );

        $updateHandler(
            new UpdateProductInput(
                productId: $secondId,
                name: 'Second Product',
                sku: new Sku('SKU-FIRST'),
                unit: null,
                costPrice: null,
                lowStockThreshold: 0,
                note: null,
            ),
        );
    }

    public function testChangeProductPriceOnlyChangesSellingPrice(): void
    {
        $createHandler = $this->createProductHandler();

        $priceHandler = $this->changeProductPriceHandler();

        $productId = $createHandler(
            new CreateProductInput(
                name: 'Price Product',
                sellingPrice: Money::fromDecimal('100000.00'),
                costPrice: Money::fromDecimal('60000.00'),
                lowStockThreshold: 5,
            ),
        );

        $this->entityManager->clear();

        $product = $this->getProduct($productId);
        $product->setStockQuantityForAdjustment(17);
        $this->entityManager->flush();

        $priceHandler(
            new ChangeProductPriceInput(
                productId: $productId,
                sellingPrice: Money::fromDecimal('125000.00'),
            ),
        );

        $this->entityManager->clear();

        $product = $this->getProduct($productId);

        self::assertSame(
            '125000.00',
            $product->getSellingPrice()->toDecimal(),
        );
        self::assertSame(
            '60000.00',
            $product->getCostPrice()?->toDecimal(),
        );
        self::assertSame(17, $product->getStockQuantity());
        self::assertSame(5, $product->getLowStockThreshold());
    }

    public function testChangeProductPriceRejectsUnknownProduct(): void
    {
        $handler = $this->changeProductPriceHandler();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Product 999999 was not found.',
        );

        $handler(
            new ChangeProductPriceInput(
                productId: 999999,
                sellingPrice: Money::fromDecimal('10000.00'),
            ),
        );
    }

    public function testDeactivateProductChangesActiveState(): void
    {
        $createHandler = $this->createProductHandler();

        $deactivateHandler = $this->deactivateProductHandler();

        $productId = $createHandler(
            new CreateProductInput(
                name: 'Deactivate Product',
                sellingPrice: Money::fromDecimal('10000.00'),
            ),
        );

        $this->entityManager->clear();

        self::assertTrue(
            $this->getProduct($productId)->isActive(),
        );

        $deactivateHandler(
            new DeactivateProductInput(
                productId: $productId,
            ),
        );

        $this->entityManager->clear();

        self::assertFalse(
            $this->getProduct($productId)->isActive(),
        );
    }

    public function testDeactivateProductIsIdempotentAtDomainStateLevel(): void
    {
        $createHandler = $this->createProductHandler();

        $deactivateHandler = $this->deactivateProductHandler();

        $productId = $createHandler(
            new CreateProductInput(
                name: 'Idempotent Deactivate',
                sellingPrice: Money::fromDecimal('10000.00'),
            ),
        );

        $deactivateHandler(
            new DeactivateProductInput(
                productId: $productId,
            ),
        );

        $deactivateHandler(
            new DeactivateProductInput(
                productId: $productId,
            ),
        );

        $this->entityManager->clear();

        self::assertFalse(
            $this->getProduct($productId)->isActive(),
        );
    }

    public function testActivateProductRestoresInactiveProduct(): void
    {
        $createHandler = $this->createProductHandler();

        $deactivateHandler = $this->deactivateProductHandler();

        $activateHandler = $this->activateProductHandler();

        $productId = $createHandler(
            new CreateProductInput(
                name: 'Activate Product',
                sellingPrice: Money::fromDecimal('10000.00'),
            ),
        );

        $deactivateHandler(
            new DeactivateProductInput(
                productId: $productId,
            ),
        );

        $this->entityManager->clear();

        self::assertFalse(
            $this->getProduct($productId)->isActive(),
        );

        $activateHandler(
            new ActivateProductInput(
                productId: $productId,
            ),
        );

        $this->entityManager->clear();

        self::assertTrue(
            $this->getProduct($productId)->isActive(),
        );
    }

    public function testActivateProductIsIdempotentWhenAlreadyActive(): void
    {
        $createHandler = $this->createProductHandler();

        $activateHandler = $this->activateProductHandler();

        $productId = $createHandler(
            new CreateProductInput(
                name: 'Already Active',
                sellingPrice: Money::fromDecimal('10000.00'),
            ),
        );

        $activateHandler(
            new ActivateProductInput(
                productId: $productId,
            ),
        );

        $this->entityManager->clear();

        self::assertTrue(
            $this->getProduct($productId)->isActive(),
        );
    }

    public function testProductCommandsRejectInvalidProductId(): void
    {
        $activateHandler = $this->activateProductHandler();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Product ID must be greater than zero.',
        );

        $activateHandler(
            new ActivateProductInput(
                productId: 0,
            ),
        );
    }

    private function getProduct(int $productId): Product
    {
        /** @var Product|null $product */
        $product = $this->entityManager
            ->getRepository(Product::class)
            ->find($productId);

        self::assertNotNull($product);

        return $product;
    }

    private function createProductHandler(): CreateProductHandler
    {
        return new CreateProductHandler(
            self::getContainer()->get(
                TransactionManagerInterface::class,
            ),
            self::getContainer()->get(
                ProductRepositoryInterface::class,
            ),
        );
    }

    private function updateProductHandler(): UpdateProductHandler
    {
        return new UpdateProductHandler(
            self::getContainer()->get(
                TransactionManagerInterface::class,
            ),
            self::getContainer()->get(
                ProductRepositoryInterface::class,
            ),
        );
    }

    private function changeProductPriceHandler(): ChangeProductPriceHandler
    {
        return new ChangeProductPriceHandler(
            self::getContainer()->get(
                TransactionManagerInterface::class,
            ),
            self::getContainer()->get(
                ProductRepositoryInterface::class,
            ),
        );
    }

    private function activateProductHandler(): ActivateProductHandler
    {
        return new ActivateProductHandler(
            self::getContainer()->get(
                TransactionManagerInterface::class,
            ),
            self::getContainer()->get(
                ProductRepositoryInterface::class,
            ),
        );
    }

    private function deactivateProductHandler(): DeactivateProductHandler
    {
        return new DeactivateProductHandler(
            self::getContainer()->get(
                TransactionManagerInterface::class,
            ),
            self::getContainer()->get(
                ProductRepositoryInterface::class,
            ),
        );
    }
}
