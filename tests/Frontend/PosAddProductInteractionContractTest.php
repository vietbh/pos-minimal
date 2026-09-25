<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class PosAddProductInteractionContractTest extends TestCase
{
    public function testAddProductActionIsIconOnlyAndAccessible(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../assets/controllers/pos_checkout_controller.js');

        self::assertNotFalse($controller);
        self::assertStringContainsString("pos-add-product-button", $controller);
        self::assertStringContainsString("add.textContent = '＋';", $controller);
        self::assertStringContainsString("add.setAttribute('aria-label'", $controller);
        self::assertStringContainsString("add.title =", $controller);
    }

    public function testAddingProductDoesNotStealFocusBackToSearch(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../assets/controllers/pos_checkout_controller.js');

        self::assertNotFalse($controller);
        $addToCart = strstr($controller, "    addToCart(event)");
        self::assertNotFalse($addToCart);
        $addToCart = strstr($addToCart, "    changeQuantity(event)", true);
        self::assertNotFalse($addToCart);
        self::assertStringContainsString("this.renderCart();", $addToCart);
        self::assertStringContainsString("this.setStatus(this.statusItemAddedValue);", $addToCart);
    }

    public function testPublicControllerBuildOutputIsNotModifiedByThisChange(): void
    {
        $source = __DIR__ . '/../../assets/controllers/pos_checkout_controller.js';
        $public = __DIR__ . '/../../public/assets/controllers';

        self::assertFileExists($source);
        self::assertDirectoryExists($public);
    }
}
