<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class Phase1010FinalRegressionContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testPhase10UiSurfaceContainsAllImplementedAcceptanceContracts(): void
    {
        $requiredFiles = [
            'templates/layout/authenticated.html.twig',
            'templates/layout/pos.html.twig',
            'templates/pos/index.html.twig',
            'templates/order/show.html.twig',
            'templates/statistics/index.html.twig',
            'assets/styles/phase-uiux-7.css',
            'assets/controllers/pos_checkout_controller.js',
            'assets/controllers/order_lifecycle_controller.js',
            'assets/controllers/statistics_filter_controller.js',
            'tests/Frontend/Phase108CriticalBusinessFlowContractTest.php',
            'tests/Frontend/Phase109MobileAcceptanceHardeningContractTest.php',
        ];

        foreach ($requiredFiles as $file) {
            self::assertFileExists($this->root . '/' . $file, $file);
        }
    }

    public function testCheckoutSecurityAndServerAuthorityRemainIntact(): void
    {
        $checkoutController = (string) file_get_contents($this->root . '/assets/controllers/pos_checkout_controller.js');
        $checkoutTemplate = (string) file_get_contents($this->root . '/templates/pos/index.html.twig');
        $checkoutBackend = (string) file_get_contents($this->root . '/src/Controller/Order/CheckoutController.php');

        self::assertStringContainsString("'X-CSRF-TOKEN'", $checkoutController);
        self::assertStringContainsString("'Idempotency-Key'", $checkoutController);
        self::assertStringContainsString('/app/payment-sessions/', $checkoutController);
        self::assertStringContainsString('data-pos-checkout-endpoint-value=', $checkoutTemplate);
        self::assertStringContainsString('data-pos-checkout-messages-value=', $checkoutTemplate);
        self::assertStringContainsString('CsrfTokenManagerInterface', $checkoutBackend);
        self::assertStringContainsString('completeOrder', $checkoutBackend);
        self::assertStringContainsString('manualBankPaymentConfirm', $checkoutBackend);
    }

    public function testOrderLifecycleKeepsPermissionAndMutationProtection(): void
    {
        $controller = (string) file_get_contents($this->root . '/src/Controller/Order/OrderLifecycleController.php');
        $frontend = (string) file_get_contents($this->root . '/assets/controllers/order_lifecycle_controller.js');
        $template = (string) file_get_contents($this->root . '/templates/order/show.html.twig');

        self::assertStringContainsString('Permission::ORDER_CANCEL', $controller);
        self::assertStringContainsString('Permission::ORDER_REFUND', $controller);
        self::assertStringContainsString('Idempotency-Key', $controller);
        self::assertStringContainsString('CsrfToken', $controller);
        self::assertStringContainsString("'X-CSRF-TOKEN'", $frontend);
        self::assertStringContainsString("'Idempotency-Key'", $frontend);
        self::assertStringContainsString('data-order-lifecycle-error-messages-value=', $template);
    }

    public function testMobileAndAccessibilityContractsRemainPresent(): void
    {
        $css = (string) file_get_contents($this->root . '/assets/styles/phase-uiux-7.css');
        $authenticated = (string) file_get_contents($this->root . '/templates/layout/authenticated.html.twig');
        $pos = (string) file_get_contents($this->root . '/templates/layout/pos.html.twig');

        self::assertStringContainsString('@media (max-width: 390px)', $css);
        self::assertStringContainsString('@media (max-width: 360px)', $css);
        self::assertStringContainsString('min-height: 48px', $css);
        self::assertStringContainsString('min-height: 52px', $css);
        self::assertStringContainsString('env(safe-area-inset-bottom)', $css);
        self::assertStringContainsString('aria-current="page"', $authenticated);
        self::assertStringContainsString('aria-controls="app-navigation"', $authenticated);
        self::assertStringContainsString('mobile-bottom-nav', $pos);
    }

    public function testStatisticsAndSecurityUiRemainBackendGated(): void
    {
        $statisticsController = (string) file_get_contents($this->root . '/src/Controller/Statistics/StatisticsController.php');
        $statisticsTemplate = (string) file_get_contents($this->root . '/templates/statistics/index.html.twig');
        $forbiddenTemplate = (string) file_get_contents($this->root . '/templates/bundles/TwigBundle/Exception/error403.html.twig');

        self::assertStringContainsString('Permission::STATISTICS_VIEW', $statisticsController);
        self::assertStringContainsString('method="get"', $statisticsTemplate);
        self::assertStringContainsString('aria-live="polite"', $statisticsTemplate);
        self::assertStringContainsString('|money_vnd', $statisticsTemplate);
        self::assertStringContainsString('security-denied', $forbiddenTemplate);
    }

    public function testFrontendControllersDoNotContainServerSideBusinessMutationRules(): void
    {
        foreach (glob($this->root . '/assets/controllers/*.js') ?: [] as $controllerPath) {
            $source = (string) file_get_contents($controllerPath);
            self::assertStringNotContainsString('EntityManager', $source, basename($controllerPath));
            self::assertStringNotContainsString('stockQuantity -=', $source, basename($controllerPath));
            self::assertStringNotContainsString('order.status =', $source, basename($controllerPath));
            self::assertStringNotContainsString('payment.status =', $source, basename($controllerPath));
        }
    }

    public function testSourceControllersStayUnderAssetsAndNoNestedPublicControllerPathExists(): void
    {
        self::assertDirectoryExists($this->root . '/assets/controllers');
        self::assertDirectoryDoesNotExist($this->root . '/assets/controllers/public/assets/controllers');
    }
}
