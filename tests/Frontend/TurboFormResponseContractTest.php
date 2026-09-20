<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class TurboFormResponseContractTest extends TestCase
{
    public function testProductCreateAndEditReturn422WhenPostIsReRendered(): void
    {
        $source = file_get_contents(__DIR__ . '/../../src/Controller/Admin/ProductController.php');
        self::assertIsString($source);

        self::assertStringContainsString('$response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);', $source);
        self::assertSame(2, substr_count($source, "return \$this->redirectToRoute('admin_products_show'"));
    }

    public function testProductCategoryCreateAndEditReturn422WhenPostIsReRendered(): void
    {
        $source = file_get_contents(__DIR__ . '/../../src/Controller/Admin/ProductCategoryController.php');
        self::assertIsString($source);

        self::assertSame(2, substr_count($source, 'Response::HTTP_UNPROCESSABLE_ENTITY'));
        self::assertSame(2, substr_count($source, "return \$this->redirectToRoute('admin_product_categories')"));
    }

    public function testSuccessfulPostPathsKeepRedirectBehavior(): void
    {
        $product = file_get_contents(__DIR__ . '/../../src/Controller/Admin/ProductController.php');
        $category = file_get_contents(__DIR__ . '/../../src/Controller/Admin/ProductCategoryController.php');

        self::assertIsString($product);
        self::assertIsString($category);

        self::assertStringContainsString("\$this->addFlash('success', 'Product created.');", $product);
        self::assertStringContainsString("\$this->addFlash('success', 'Product updated.');", $product);
        self::assertStringContainsString("\$this->addFlash('success','Category created.');", $category);
        self::assertStringContainsString("\$this->addFlash('success','Category updated.');", $category);
    }
}
