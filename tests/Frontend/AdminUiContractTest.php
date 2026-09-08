<?php

declare(strict_types=1);
namespace App\Tests\Frontend;
use PHPUnit\Framework\TestCase;
final class AdminUiContractTest extends TestCase
{
    public function testAdminUiUsesPermissionAwareActionsAndCsrf(): void
    {
        $root=dirname(__DIR__,2);
        $product=file_get_contents($root.'/templates/admin/product/show.html.twig');
        $stock=file_get_contents($root.'/templates/admin/stock/show.html.twig');
        $customer=file_get_contents($root.'/templates/customer/show.html.twig');
        self::assertNotFalse($product); self::assertNotFalse($stock); self::assertNotFalse($customer);
        foreach ([$product,$stock,$customer] as $source) self::assertStringContainsString('is_granted(', $source);
        self::assertStringContainsString("csrf_token('admin_product_state')",$product);
        self::assertStringContainsString("csrf_token('admin_product_price')",$product);
        self::assertStringContainsString("csrf_token('admin_stock_adjust')",$stock);
        self::assertStringContainsString("csrf_token('admin_customer_form')",file_get_contents($root.'/templates/customer/form.html.twig'));
    }
    public function testAdminUiDoesNotImplementAuthoritativeBusinessRules(): void
    {
        $root=dirname(__DIR__,2);
        foreach (['templates/admin/product','templates/admin/stock','templates/customer'] as $dir) {
            $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir));
            foreach($it as $file){if(!$file->isFile())continue;$source=file_get_contents($file->getPathname());foreach(['calculateStock','calculateTotal','validateStock','calculateDebt','decidePaymentValidity'] as $forbidden)self::assertStringNotContainsString($forbidden,$source);}
        }
    }
}
