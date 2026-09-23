<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class Phase104OrderCustomerDebtUiContractTest extends TestCase
{
    public function testOrderCustomerDebtScreensUseAccessibleMobilePrimitives(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'templates/order/index.html.twig',
            'templates/order/show.html.twig',
            'templates/customer/index.html.twig',
            'templates/customer/show.html.twig',
            'templates/customer/form.html.twig',
            'templates/debt/index.html.twig',
            'templates/debt/show.html.twig',
        ] as $file) {
            $content = file_get_contents($root.'/'.$file);
            self::assertNotFalse($content);
            self::assertStringContainsString('business-', $content);
        }

        self::assertStringContainsString('role="search"', file_get_contents($root.'/templates/customer/index.html.twig'));
        self::assertStringContainsString('role="search"', file_get_contents($root.'/templates/debt/index.html.twig'));
        self::assertStringContainsString('aria-current="page"', file_get_contents($root.'/templates/debt/index.html.twig'));
    }

    public function testFrontendDoesNotReimplementFinancialOrAuthorizationRules(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [
            $root.'/templates/order/index.html.twig',
            $root.'/templates/order/show.html.twig',
            $root.'/templates/customer/index.html.twig',
            $root.'/templates/customer/show.html.twig',
            $root.'/templates/debt/index.html.twig',
            $root.'/templates/debt/show.html.twig',
            $root.'/assets/controllers/debt_payment_controller.js',
            $root.'/assets/controllers/order_lifecycle_controller.js',
        ];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            self::assertNotFalse($content);
            foreach (['calculateDebt', 'debtRemaining =', 'remainingAmount =', 'debtOriginal -'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $content);
            }
        }
        $order = file_get_contents($root.'/templates/order/show.html.twig');
        self::assertStringContainsString("is_granted('ORDER_CANCEL')", $order);
        self::assertStringContainsString("is_granted('ORDER_REFUND')", $order);
        $customer = file_get_contents($root.'/templates/customer/show.html.twig');
        self::assertStringContainsString("is_granted('DEBT_VIEW')", $customer);
        self::assertStringContainsString("is_granted('ORDER_VIEW')", $customer);
    }

    public function testMutatingControllersHideTechnicalErrorCodesAndPreserveIdempotency(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['assets/controllers/order_lifecycle_controller.js', 'assets/controllers/debt_payment_controller.js'] as $file) {
            $content = file_get_contents($root.'/'.$file);
            self::assertNotFalse($content);
            self::assertStringContainsString("'Idempotency-Key'", $content);
            self::assertStringContainsString("'X-Request-ID'", $content);
            self::assertStringNotContainsString('errorCode', $content);
        }
        $debt = file_get_contents($root.'/assets/controllers/debt_payment_controller.js');
        self::assertStringContainsString('successTarget.focus()', $debt);
    }

    public function testBusinessStatusHasNonColorMarkerFoundation(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/assets/styles/app.css');
        self::assertNotFalse($css);
        self::assertStringContainsString('.business-status::before', $css);
        self::assertStringContainsString('[data-status="PAID"]', $css);
        self::assertStringContainsString('[data-status="REVERSED"]', $css);
        self::assertStringContainsString('@media(max-width:380px)', $css);
    }
}
