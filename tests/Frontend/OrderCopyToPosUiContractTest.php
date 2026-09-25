<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class OrderCopyToPosUiContractTest extends TestCase
{
    public function testOrderDetailExposesCopyToPosWithoutEditingTheSourceOrder(): void
    {
        $root = dirname(__DIR__, 2);
        $show = file_get_contents($root.'/templates/order/show.html.twig');

        self::assertNotFalse($show);
        self::assertStringContainsString("is_granted('POS_ACCESS')", $show);
        self::assertStringContainsString("path('pos', {copyFromOrder: order.id})", $show);
        self::assertStringContainsString("'order.copy_to_pos.help'|trans", $show);
        self::assertStringNotContainsString("path('order_edit'", $show);
    }

    public function testPosControllerLoadsCopyPayloadFromServer(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root.'/assets/controllers/pos_checkout_controller.js');
        $template = file_get_contents($root.'/templates/pos/index.html.twig');

        self::assertNotFalse($controller);
        self::assertNotFalse($template);
        self::assertStringContainsString('loadCopiedOrder()', $controller);
        self::assertStringContainsString('cashTenderedAmount', $controller);
        self::assertStringContainsString('copyFromOrderUrlBase', $template);
        self::assertStringContainsString('copySourceNotice', $template);
    }
}
