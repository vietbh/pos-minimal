<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class Phase105ProductStockUiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testProductAndStockTemplatesUseAccessibleControlsAndStatusMarkers(): void
    {
        $productIndex = (string) file_get_contents($this->root . '/templates/admin/product/index.html.twig');
        $productShow = (string) file_get_contents($this->root . '/templates/admin/product/show.html.twig');
        $productForm = (string) file_get_contents($this->root . '/templates/admin/product/form.html.twig');
        $stockIndex = (string) file_get_contents($this->root . '/templates/admin/stock/index.html.twig');
        $stockShow = (string) file_get_contents($this->root . '/templates/admin/stock/show.html.twig');

        self::assertStringContainsString('aria-describedby="product-list-help"', $productIndex);
        self::assertStringContainsString("'admin.no_products_match'|trans", $productIndex);
        self::assertStringContainsString('admin-status--warning', $productIndex);
        self::assertStringContainsString("'admin.no_sku'|trans", $productIndex);

        self::assertStringContainsString("'admin.product_summary'|trans", $productShow);
        self::assertStringContainsString("'admin.change_price'|trans", $productShow);
        self::assertStringContainsString('scope="col"', $productShow);

        self::assertStringContainsString('id="product-name"', $productForm);
        self::assertStringContainsString('id="product-selling-price"', $productForm);
        self::assertStringContainsString('aria-describedby="product-form-help"', $productForm);

        self::assertStringContainsString('aria-describedby="stock-list-help"', $stockIndex);
        self::assertStringContainsString("'admin.stock_products_count'|trans", $stockIndex);
        self::assertStringContainsString("'admin.page_of'|trans", $stockIndex);

        self::assertStringContainsString("'admin.stock_summary'|trans", $stockShow);
        self::assertStringContainsString('data-controller="stock-adjust"', $stockShow);
        self::assertStringContainsString('data-stock-adjust-target="submit"', $stockShow);
        self::assertStringContainsString('scope="col"', $stockShow);
    }

    public function testStockPresentationControllerContainsNoBusinessRules(): void
    {
        $controller = (string) file_get_contents($this->root . '/assets/controllers/stock_adjust_controller.js');

        self::assertStringContainsString('aria-busy', $controller);
        self::assertStringContainsString('disabled', $controller);
        self::assertStringNotContainsString('fetch(', $controller);
        self::assertStringNotContainsString('stockQuantity', $controller);
        self::assertStringNotContainsString('quantityChange', $controller);
        self::assertStringNotContainsString('price', $controller);
    }

    public function testStockMutationRouteRemainsProtectedAndIdempotent(): void
    {
        $controller = (string) file_get_contents($this->root . '/src/Controller/Admin/StockController.php');

        self::assertStringContainsString("Permission::STOCK_ADJUST->value", $controller);
        self::assertStringContainsString("admin_stock_adjust", $controller);
        self::assertStringContainsString("Idempotency-Key", $controller);
        self::assertStringContainsString("AdjustStockInput", $controller);
    }
}
