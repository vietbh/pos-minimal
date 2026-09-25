<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class SalesPointAdminUiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testSalesPointAdminHasGenerateAndToggleControls(): void
    {
        $html = file_get_contents($this->root.'/templates/admin/sales_points/index.html.twig');
        $js = file_get_contents($this->root.'/assets/controllers/sales_point_form_controller.js');
        $controller = file_get_contents($this->root.'/src/Controller/Admin/SalesPointController.php');

        self::assertStringContainsString("sales-point-form#generateCode", $html);
        self::assertStringContainsString("admin_sales_point_toggle", $html);
        self::assertStringContainsString("csrf_token('admin_sales_point_toggle')", $html);
        self::assertStringContainsString('generateCode()', $js);
        self::assertStringContainsString("Route('/{id<\\d+>}/toggle'", $controller);
        self::assertStringContainsString('setActive(!$point->isActive())', $controller);
    }

    public function testPaymentAccountTogglePostsToItsToggleRoute(): void
    {
        $html = file_get_contents($this->root.'/templates/admin/payment/accounts.html.twig');

        self::assertStringContainsString("path('admin_payment_account_toggle',{id:account.id})", $html);
        self::assertStringContainsString("csrf_token('admin_payment_account_toggle')", $html);
    }
}
